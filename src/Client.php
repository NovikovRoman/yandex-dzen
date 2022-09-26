<?php

namespace YandexDzen;

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;

class Client
{
    const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36';
    const URL_AUTH_FORM = 'https://passport.yandex.ru/auth?origin=dzen&retpath=https%3A%2F%2Fsso.passport.yandex.ru%2Fpush%3Fuuid%3D{uid}%26retpath%3Dhttps%253A%252F%252Fdzen.ru%252F&backpath=https%3A%2F%2Fdzen.ru%2F';

    private $login;
    private $password;

    private $cookieJar;
    private $clientHttp;
    private $httpParams = [
        'timeout' => 30,
        'allow_redirects' => [
            'max' => 5,
            'strict' => false,
            'referer' => false,
            'protocols' => ['http', 'https'],
            'track_redirects' => false,
        ],
        'headers' => [
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'User-Agent' => self::USER_AGENT,
        ],
        'verify' => false,
    ];

    public function __construct($login, $pass)
    {
        $this->login = $login;
        $this->password = $pass;
        $this->clientHttp = new \GuzzleHttp\Client();
        $this->cookieJar = new CookieJar();
        $this->httpParams['cookies'] = $this->cookieJar;
    }

    public function setToken($token)
    {
        $this->httpParams['headers']['X-Csrf-Token'] = $token;
    }

    /**
     * @throws GuzzleException
     * @throws YandexDzenException|InvalidSelectorException
     */
    public function auth()
    {
        $html = str_replace('\u002F', '/', $this->get(YandexDzen::URL_DZEN));
        preg_match('#https://sso\.dzen\.ru/install\?uuid=([^"]+)#sui', $html, $m);
        if (empty($m[1])) {
            $e = new YandexDzenException('Не найдена ссылка для авторизации.');
            $e->setHtml($html);
            throw $e;
        }

        $html = $this->get(str_replace('{uid}', $m[1], self::URL_AUTH_FORM));

        $doc = new Document($html);
        if (!$doc->has('.passp-auth')) {
            $e = new YandexDzenException('Не удалось получить форму авторизации.');
            $e->setHtml($html);
            throw $e;
        }

        if (!preg_match('/process_uuid=([a-f0-9\-]+)/sui', $html, $m)) {
            $e = new YandexDzenException('Не удалось получить process_uuid.');
            $e->setHtml($html);
            throw $e;
        }

        if (!$doc->has('input[name="retpath"]')) {
            $e = new YandexDzenException('Не найден retpath.');
            $e->setHtml($html);
            throw $e;
        }

        $retPath = $doc->first('input[name="retpath"]')->attr('value') ?: '';

        $processUUID = $m[1];
        // получим токен и сделаем запрос с логином
        $token = $doc->first('.passp-auth input[name=csrf_token]')->attr('value');
        $this->setToken($token);
        $resp = $this->sendLogin($token, $processUUID, $retPath);

        // сделаем запрос с паролем
        $this->sendPassword($token, $resp['track_id'], $retPath);

        $this->sendRedirect($retPath);
    }

    /**
     * @param $token
     * @param $processUUID
     * @param $retpath
     * @return array
     * fields:
     * [
     *    status string
     *    csrf_token string
     *    can_authorize bool
     *    preferred_auth_method string
     *    auth_methods    []string
     *    track_id string
     *    id string
     * ]
     * @throws GuzzleException
     * @throws YandexDzenException
     */
    private function sendLogin($token, $processUUID, $retpath)
    {
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $html = $this->post('https://passport.yandex.ru/registration-validations/auth/multi_step/start', [
            'csrf_token' => $token,
            'login' => $this->login,
            'process_uuid' => $processUUID,
            'retpath' => $retpath,
            'origin' => 'dzen',
        ], $headers);
        $resp = json_decode($html, true);
        if (empty($resp['status']) || $resp['status'] != 'ok') {
            $e = new YandexDzenException('Ошибка отправки логина.');
            $e->setHtml($html);
            throw $e;
        }
        return $resp;
    }

    /**
     * @param $token
     * @param $trackID
     * @param $retpath
     * @return void
     * @throws GuzzleException
     * @throws YandexDzenException
     */
    private function sendPassword($token, $trackID, $retpath)
    {
        $html = $this->post('https://passport.yandex.ru/registration-validations/auth/multi_step/commit_password', [
            'csrf_token' => $token,
            'track_id' => $trackID,
            'password' => $this->password,
            'retpath' => $retpath,
        ]);

        $resp = json_decode($html, true);
        if (empty($resp['status']) || $resp['status'] != 'ok') {
            $e = new YandexDzenException('Ошибка отправки пароля.');
            $e->setHtml($html);
            throw $e;
        }
    }

    /**
     * @param $retpath
     * @throws GuzzleException
     * @throws YandexDzenException
     */
    private function sendRedirect($retpath)
    {
        $html = str_replace('\u002F', '/', $this->get($retpath));
        preg_match(
            '#{"host":"([^"]+).+?"goal":"([^>]+)".+?[\'"](\d+\.\d+\.[a-z0-9._\-]+)#sui', $html, $m);
        if (count($m) == 0) {
            $e = new YandexDzenException('Не найдена ссылка sync, goal и данные container.');
            $e->setHtml($html);
            throw $e;
        }

        $html = $this->post($m[1], [
            'goal' => $m[2],
            'container' => $m[3],
        ]);

        $html = str_replace('\u002F', '/', $html);
        preg_match('#"finish":"(https:[^"]+)#sui', $html, $m);
        if (empty($m)) {
            $e = new YandexDzenException('Не найдена ссылка finish.');
            $e->setHtml($html);
            throw $e;
        }
        $html = str_replace('\u002F', '/', $this->get($m[1]));
        preg_match('#{"host":"([^"]+).+?"retpath":"([^"]+)".+?[\'"](\d+\.\d+\.[a-z0-9._\-]+)#sui',
            $html, $m);
        if (empty($m)) {
            $e = new YandexDzenException('Не найдена ссылка install.');
            $e->setHtml($html);
            throw $e;
        }

        $this->post($m[1], [
            'retpath' => $m[2],
            'container' => $m[3],
        ]);
    }

    /**
     * @param $url
     * @param array $headers
     * @return string
     * @throws GuzzleException
     */
    public function get($url, $headers = [])
    {
        if (!empty($this->httpParams['form_params'])) {
            unset($this->httpParams['form_params']);
        }
        if (!empty($headers)) {
            $this->httpParams['headers'] = array_merge($this->httpParams['headers'], $headers);
        }
        $res = $this->clientHttp->request('GET', $url, $this->httpParams);
        return $res->getBody()->getContents();
    }

    /**
     * @param $url
     * @param $params
     * @param array $headers
     * @return string
     * @throws GuzzleException
     */
    public function post($url, $params, $headers = [])
    {
        if (!empty($headers)) {
            $this->httpParams['headers'] = array_merge($this->httpParams['headers'], $headers);
        }

        $this->httpParams['form_params'] = $params;
        $res = $this->clientHttp->request('POST', $url, $this->httpParams);
        return $res->getBody()->getContents();
    }
}