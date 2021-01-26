<?php

namespace YandexDzen;

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use GuzzleHttp\Cookie\CookieJar;
use \GuzzleHttp\Exception\GuzzleException;

class Client
{
    const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36';
    const SEND_LOGIN_URL = 'https://passport.yandex.ru/auth?origin=zen_header_entry&retpath=https%3A%2F%2Fzen.yandex.ru%2F%3Fclid%3D300%26from_page%3Dfeed_header_login';

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
        $html = $this->get(self::SEND_LOGIN_URL);
        $doc = new Document($html);
        try {
            $form = $doc->first('.passp-auth-content .passp-login-form form');
            if (!$form) {
                throw new InvalidSelectorException('');
            }

        } catch (InvalidSelectorException $e) {
            $e = new YandexDzenException('Не удалось получить форму для отправки логина.');
            $e->setHtml($html);
            throw $e;
        }

        if (!preg_match('#process_uuid=([a-f0-9\-]+)#sui', $html, $m)) {
            $e = new YandexDzenException('Не удалось получить process_uuid.');
            $e->setHtml($html);
            throw $e;
        }
        $processUUID = $m[1];

        try {
            $csrfToken = $form->first('input[name=csrf_token]')->attr('value');
            $retpath = $form->first('input[name=retpath]')->attr('value');

        } catch (InvalidSelectorException $e) {
            $e = new YandexDzenException('Проблема поиска полей в форме.');
            $e->setHtml($html);
            throw $e;
        }

        // отправим login
        $resp = $this->sendLogin($csrfToken, $retpath, $processUUID);
        if (empty($resp['status']) || $resp['status'] != 'ok' || empty($resp['can_authorize'])) {
            $e = new YandexDzenException('Ошибка отправки логина.');
            $e->setHtml($html);
            throw $e;
        }

        // отправим password
        $resp = $this->sendPassword($csrfToken, $retpath, $resp['track_id']);
        if (empty($resp['status']) || $resp['status'] != 'ok') {
            $e = new YandexDzenException('Ошибка отправки пароля.');
            $e->setHtml($html);
            throw $e;
        }
    }

    /**
     * @param $csrfToken
     * @param $processUUID
     * @param $retpath
     * @return array|null
     * @throws GuzzleException
     */
    public function sendLogin($csrfToken, $retpath, $processUUID)
    {
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];
        $html = $this->post('https://passport.yandex.ru/registration-validations/auth/multi_step/start',
            [
                'csrf_token' => $csrfToken,
                'login' => $this->login,
                'process_uuid' => $processUUID,
                'retpath' => $retpath,
                'origin' => 'zen_header_entry',
            ], $headers
        );

        $res = json_decode($html, true);
        return is_array($res) ? $res : null;
    }

    /**
     * @param $csrfToken
     * @param $retpath
     * @param $trackId
     * @return array|null
     * @throws GuzzleException
     */
    public function sendPassword($csrfToken, $retpath, $trackId)
    {
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ];
        $html = $this->post('https://passport.yandex.ru/registration-validations/auth/multi_step/commit_password',
            [
                'csrf_token' => $csrfToken,
                'password' => $this->password,
                'track_id' => $trackId,
                'retpath' => $retpath,
            ], $headers
        );

        $resp = json_decode($html, true);
        return is_array($resp) ? $resp : null;
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