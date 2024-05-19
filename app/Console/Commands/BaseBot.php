<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

abstract class BaseBot extends Command
{
    protected bool $isWindows = PHP_OS_FAMILY === 'Windows';

    protected function getURL(string $url, array $headers, callable $on_timeout = null, callable $on_404 = null): ?string
    {
        return $this->reqURL('GET', $url, $headers, null, $on_timeout, $on_404);
    }

    protected function postURL(string $url, string $body, array $headers, callable $on_timeout = null, callable $on_404 = null): ?string
    {
        return $this->reqURL('POST', $url, $headers, $body, $on_timeout, $on_404);
    }

    private function fi2wheres(array $fi): array
    {
        $wheres = [];

        foreach ($fi as $k => $v) {
            $wheres[] = [$k, '=', $v];
        }

        return $wheres;
    }

    protected function getURLWithDB(
        string $url,
        array $headers,
        string $table,
        array $findOrInsert,
        callable $forceHttp = null,
        callable $respMiddleware = null
    ): mixed
    {
        DB::select("PRAGMA synchronous = OFF");

        if ($forceHttp === null || $forceHttp() !== true) {
            $vv = DB::table($table)->where($this->fi2wheres($findOrInsert))->value('vv');

            if ($vv !== null && $vv !== "") {
                $this->line("read vv from db");
                return $vv;
            }

            $this->warn("can not find vv from db");
        }

        $resp = $this->getURL(
            $url,
            $headers,
            on_timeout: fn() => DB::table($table)->insert(array_merge($findOrInsert, [
                'vv' => '__timeout__',
            ])),
            on_404: fn() => DB::table($table)->insert(array_merge($findOrInsert, [
                'vv' => '__404__',
            ])),
        );

        if ($respMiddleware !== null) {
            $resp = $respMiddleware($resp);
        }

        if ($resp === null) {
            return null;
        }

        $ok = DB::table($table)->insert(array_merge($findOrInsert, [
            'vv' => $resp,
        ]));

        if ($ok) {
            $this->info("db insert successful");
        } else {
            $this->warn("db insert failed");
        }

        return DB::table($table)->where($this->fi2wheres($findOrInsert))->value('vv');
    }

    public ResponseInterface|null $sf_response = null;

    private function reqURL(
        string $method,
        string $url,
        array $headers,
        string|callable|iterable $body = null,
        callable $on_timeout = null,
        callable $on_404 = null
    ): ?string
    {
        $fragment = parse_url(rawurldecode($url), PHP_URL_FRAGMENT);

        $this->line("[$method]: [$fragment]");

        $this->sf_response = null;

        $sleep = $this->option('sleep');
        if (!is_numeric($sleep)) {
            $sleep = 6.1;
        }

        usleep((int)($sleep * 100000));

        $sfh = HttpClient::create();

        $options = [
            'headers' => $headers,
            'max_redirects' => 3,
            'timeout' => 15.0,
            'http_version' => '2.0',
        ];

        if ($body !== null) {
            $options['body'] = $body;
        }

        $is_tried = false;

        send_request:

        try {
            $resp = $sfh->request($method, $url, $options);
        } catch (TransportExceptionInterface $e) {
            $this->warn(__LINE__ . " TransportExceptionInterface " . $e->getMessage());
            return null;
        } catch (Throwable $e) {
            $this->warn(__LINE__ . " Throwable " . $e->getMessage());
            return null;
        }

        $this->sf_response = $resp;

        try {
            $statusCode = $resp->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $this->warn(__LINE__ . " TransportExceptionInterface " . $e->getMessage());

            if (str_contains($e->getMessage(), 'Idle timeout reached')) {
                $on_timeout && $on_timeout();
                return null;
            }

            if (!$is_tried) {
                $is_tried = true;
                goto send_request;
            }

            return null;
        }

        if ($statusCode !== 200) {
            $this->warn(__LINE__ . " statusCode: $statusCode");

            if ($statusCode === 404) {
                $on_404 && $on_404();
                return null;
            }

            if (!$is_tried) {
                $is_tried = true;
                goto send_request;
            }

            return null;
        }

        try {
            $respContent = $resp->getContent();
        } catch (ClientExceptionInterface $e) {
            $this->warn(__LINE__ . " ClientExceptionInterface " . $e->getMessage());
            return null;
        } catch (RedirectionExceptionInterface $e) {
            $this->warn(__LINE__ . " RedirectionExceptionInterface " . $e->getMessage());
            return null;
        } catch (ServerExceptionInterface $e) {
            $this->warn(__LINE__ . " ServerExceptionInterface " . $e->getMessage());
            return null;
        } catch (TransportExceptionInterface $e) {
            $this->warn(__LINE__ . " TransportExceptionInterface " . $e->getMessage());
            return null;
        }

        $respContentEncoding = $resp->getHeaders(false)['content-encoding'][0] ?? null;

        if ($respContentEncoding === 'gzip') {
            try {
                $respContent = gzdecode($respContent);
            } catch (Throwable) {
                $this->warn("gzdecode() failed");
            }
        }

        if (!is_string($respContent) || trim($respContent) === '') {
            $this->warn("empty resp content");
            return null;
        }

        return $respContent;
    }
}
