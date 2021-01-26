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
 * Last modified: 2020.08.14 at 19:14
 */

declare(strict_types=1);


namespace HuR\WebhubProxy\Middleware;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HeaderAdjustmentMiddleware implements MiddlewareInterface
{
    public const FORBIDDEN_RESPONSE_HEADERS = [
        'expect-ct',
        'report-to',
        'nel',
        'vary',
        'server',
        'transfer-encoding',
        'set-cookie'
    ];

    public const FORBIDDEN_RESPONSE_HEADER_PREFIXES = [
        'cf-'
    ];

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $this->removeUnwantedRequestHeaders($request);
        $request = $request->withHeader('x-webhub-external-proxy', 1);

        $response = $handler->handle($request);

        return $this->removeUnwantedResponseHeaders($response);
    }

    /**
     * Internal helper to remove unwanted information that should not be passed to the HuR infrastructure.
     *
     * @param   \Psr\Http\Message\ServerRequestInterface  $request
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function removeUnwantedRequestHeaders(ServerRequestInterface $request): ServerRequestInterface {

        // Remove all cookies, so we never get any information we should not get
        $request = $request->withCookieParams([]);
        $request = $request->withoutHeader('cookie');

        return $request;
    }

    /**
     * Internal helper to filter out all privacy relevant headers we don't want to transfer to the real user
     *
     * @param   \Psr\Http\Message\ResponseInterface  $response
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function removeUnwantedResponseHeaders(ResponseInterface $response): ResponseInterface{
        $headers = $response->getHeaders();

        foreach ($headers as $name => $content){
            $lcName = strtolower($name);

            if(in_array($lcName, static::FORBIDDEN_RESPONSE_HEADERS, true)){
                $response = $response->withoutHeader($name);
                continue;
            }

            foreach (static::FORBIDDEN_RESPONSE_HEADER_PREFIXES as $prefix){
                if(strpos($lcName, $prefix) === 0) {
                    $response = $response->withoutHeader($name);
                    continue 2;
                }
            }

        }

        return $response;
    }
}
