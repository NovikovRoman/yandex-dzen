<?php

namespace YandexDzen;

use DiDom\Document;
use GuzzleHttp\Cookie\CookieJar;
use \GuzzleHttp\Exception\GuzzleException;

class Client
{
    const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36';
    const URL_AUTH_FORM = 'https://passport.yandex.ru/auth?retpath=https%3A%2F%2Fzen.yandex.ru%2Fmedia%2Fzen%2Flogin';

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
     * @throws YandexDzenException
     */
    public function auth()
    {
        $html = $this->get(self::URL_AUTH_FORM);
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
        $processUUID = $m[1];
        // получим токен и сделаем запрос с логином
        $token = $doc->first('.passp-auth input[name=csrf_token]')->attr('value');
        $this->setToken($token);
        $resp = $this->sendLogin($token, $processUUID);

        // сделаем запрос с паролем
        $this->sendPassword($token, $resp['track_id']);
    }

    /**
     * @param $token
     * @param $processUUID
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
    private function sendLogin($token, $processUUID)
    {
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
        ];
        $html = $this->post('https://passport.yandex.ru/registration-validations/auth/multi_step/start', [
            'csrf_token' => $token,
            'login' => $this->login,
            'process_uuid' => $processUUID,
            'retpath' => 'https://zen.yandex.ru/media/zen/login',
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
     * @return mixed
     * @throws GuzzleException
     * @throws YandexDzenException
     */
    private function sendPassword($token, $trackID)
    {
        $html = $this->post('https://passport.yandex.ru/registration-validations/auth/multi_step/commit_password', [
            'csrf_token' => $token,
            'track_id' => $trackID,
            'password' => $this->password,
        ]);

        $resp = json_decode($html, true);
        if (empty($resp['status']) || $resp['status'] != 'ok') {
            $e = new YandexDzenException('Ошибка отправки пароля.');
            $e->setHtml($html);
            throw $e;
        }
        return $resp;
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