<?php
/*
 * Copyright 2020 LABOR.digital
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2020.08.14 at 20:58
 */

declare(strict_types=1);


namespace HuR\WebhubProxy\Middleware;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function GuzzleHttp\Psr7\stream_for;

class JsBasePathMiddleware implements MiddlewareInterface
{
    /**
     * Optional override for the host to resolve instead of the current domain
     *
     * @var string|null
     */
    protected $hostOverride;

    /**
     * JsBasePathMiddleware constructor.
     *
     * @param   string|null  $hostOverride
     */
    public function __construct(?string $hostOverride = null)
    {
        $this->hostOverride = $hostOverride;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (strpos($request->getAttribute('requestPath'), '/js/snippet') === 0) {
            $host    = $this->hostOverride ?? $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
            $baseUrl = $host . '/' . rtrim($request->getAttribute('basePath'), '\\/');

            $isGzipped = $response->getHeaderLine('Content-Encoding') === 'gzip';
            $content   = $response->getBody()->getContents();
            $content   = $isGzipped ? gzdecode($content) : $content;

            $content = '// Script delivered by webhub privacy proxy. Base url was injected' . PHP_EOL .
                       'window.webhubBaseUrl="' . $baseUrl . '"; ' . PHP_EOL .
                       $content;

            $content = $isGzipped ? gzencode($content) : $content;

            $response = $response->withBody(stream_for($content));
        }

        return $response;
    }

}
