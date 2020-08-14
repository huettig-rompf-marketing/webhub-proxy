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
 * Last modified: 2020.08.14 at 20:53
 */

declare(strict_types=1);


namespace HuR\WebhubProxy\Middleware;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PathResolverMiddleware implements MiddlewareInterface
{
    /**
     * The root directory to strip from the incoming requests
     *
     * @var string
     */
    protected $rootDir;

    /**
     * RootDirRemoverMiddleware constructor.
     *
     * @param   string  $rootDir
     */
    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Remove the overlap of the request path and the root dir
        $absDir      = implode('/',
            array_unique(
                array_merge(
                    explode('/', $this->rootDir),
                    explode('/', $path)
                ), SORT_REGULAR
            )
        );
        $requestPath = preg_replace('~^' . preg_quote($this->rootDir, '~') . '~', '', $absDir);
        $request     = $request->withAttribute('requestPath', $requestPath);

        // Calculate the proxy base path
        $basePath = rtrim(preg_replace('~' . preg_quote($requestPath, '~') . '$~', '', $path), '\\/') . '/';
        $request  = $request->withAttribute('basePath', $basePath);

        return $handler->handle($request);
    }

}
