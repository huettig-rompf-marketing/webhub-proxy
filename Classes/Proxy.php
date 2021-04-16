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
 * Last modified: 2020.08.14 at 17:35
 */

declare(strict_types=1);


namespace HuR\WebhubProxy;


use GuzzleHttp\Psr7\Response;
use HuR\WebhubProxy\Middleware\HeaderAdjustmentMiddleware;
use HuR\WebhubProxy\Middleware\JsBasePathMiddleware;
use HuR\WebhubProxy\Middleware\PathResolverMiddleware;
use HuR\WebhubProxy\Middleware\RootDirRemoverMiddleware;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Uri;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;
use Laminas\Stratigility\MiddlewarePipe;
use Middlewares\Proxy as ProxyMiddleware;
use MongoDB\Driver\Server;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use function GuzzleHttp\Psr7\stream_for;
use function GuzzleHttp\Psr7\try_fopen;

class Proxy
{

    /**
     * The root directory we use to determine the document root with
     *
     * @var string
     */
    protected $rootDir;

    /**
     * The target url to send the requests to
     *
     * @var string
     */
    protected $targetUrl = 'https://webhub.huettig-rompf.de';

    /**
     * The instance of the proxy middleware or null if not given
     *
     * @var ProxyMiddleware|null
     */
    protected $concreteProxy;

    /**
     * A list of additional middlewares to register in the pipe
     *
     * @var \Psr\Http\Server\MiddlewareInterface[]
     */
    protected $additionalMiddlewares = [];

    /**
     * Proxy constructor.
     *
     * @param   string|null  $rootDir    The directory where the proxy script is executed.
     *                                   This should be __DIR__ for the most part.
     * @param   string|null  $targetUrl  The target url to redirect the request to -> only for dev
     */
    public function __construct(string $rootDir, ?string $targetUrl = null)
    {
        $this->rootDir = $rootDir;
        if (!empty($targetUrl)) {
            $this->targetUrl = $targetUrl;
        }
    }

    /**
     * Allows you to set up the proxy middleware instance yourself
     *
     * @param   \Middlewares\Proxy  $concreteProxy
     *
     * @return $this
     */
    public function setConcreteProxy(ProxyMiddleware $concreteProxy): self
    {
        $this->concreteProxy = $concreteProxy;

        return $this;
    }

    /**
     * Allows you to register additional middlewares
     *
     * @param   \Psr\Http\Server\MiddlewareInterface  $middleware
     *
     * @return $this
     */
    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->additionalMiddlewares[] = $middleware;

        return $this;
    }

    /**
     * Starts the proxy script
     *
     * @param   ServerRequestInterface|null  $request  Optional request to dispatch.
     *                                                 If omitted a new request is created by the server environment.
     */
    public function run(?ServerRequestInterface $request = null): void
    {
        // Configure the proxy middleware
        if ($this->concreteProxy === null) {
            $this->concreteProxy = new ProxyMiddleware(new Uri($this->targetUrl));
            $this->concreteProxy->options([
                'decode_content'  => false,
                'synchronous'     => true,
                'allow_redirects' => [
                    'max' => 3,
                ],
            ]);
        }

        // Register middlewares
        $app = new MiddlewarePipe();
        $app->pipe(new PathResolverMiddleware($this->rootDir));
        $app->pipe(new HeaderAdjustmentMiddleware());
        $app->pipe(new JsBasePathMiddleware());
        $app->pipe(new RootDirRemoverMiddleware());
        foreach ($this->additionalMiddlewares as $additionalMiddleware) {
            $app->pipe($additionalMiddleware);
        }
        $app->pipe($this->makeGuzzleMultipartPolyfillMiddleware($this->concreteProxy));
        $app->pipe($this->concreteProxy);

        // Dispatch the messaging pipeline
        $server = new RequestHandlerRunner(
            $app,
            new SapiEmitter(),
            static function () use ($request) {
                return $request ?? ServerRequestFactory::fromGlobals();
            },
            static function (Throwable $e) {
                $response = (new ResponseFactory())->createResponse(500);
                $response->getBody()->write(sprintf(
                    'An error occurred: %s',
                    $e->getMessage()
                ));

                return $response;
            }
        );
        $server->run();
    }

    /**
     * Creates a new middleware instance which automatically converts a multipart request
     * to be handled correctly by the guzzle proxy client
     * @param   \Middlewares\Proxy  $proxy
     *
     * @return \Psr\Http\Server\MiddlewareInterface
     */
    protected function makeGuzzleMultipartPolyfillMiddleware(ProxyMiddleware $proxy): MiddlewareInterface{
        return new class($proxy) implements MiddlewareInterface{
            protected $proxy;
            public function __construct(ProxyMiddleware $proxy){
                $this->proxy = $proxy;
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {

                if($request->getMethod() === 'POST' &&
                   is_array($request->getParsedBody()) &&
                   stripos($request->getHeaderLine('Content-Type'), 'multipart') !== false
                ){

                    $this->proxy->options([
                        'multipart' => $this->makeMultipartData(
                            $request->getParsedBody(),
                            $request->getUploadedFiles()
                        )
                    ]);

                    /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                    $request = $request->withoutHeader('Content-Type');
                }

                return $handler->handle($request);
            }

            /**
             * Builds the multipart data array based on the given, parsed body and uploaded files array
             * @param   array  $body
             * @param   array  $files
             *
             * @return array
             */
            protected function makeMultipartData(array $body, array $files): array{
                $data = $this->flattenArray($body);
                $fileData = $this->flattenArray($files);
                $this->prepareFileArray($fileData);
                return array_merge($data, $fileData);
            }

            /**
             * Flattens an array to the correct multipart data format
             * @param   array   $data
             * @param   string  $prefix
             *
             * @return array
             */
            protected function flattenArray(array $data, string $prefix = ''): array{
                $hasPrefix = $prefix !== '';
                $flattened = [];
                foreach ($data as $k => $v) {
                    $key = $hasPrefix ? $prefix . '[' . $k . ']' : $k;
                    if(is_array($v)){
                        /** @noinspection SlowArrayOperationsInLoopInspection */
                        $flattened = array_merge($flattened, $this->flattenArray($v, $key));
                    } else {
                        $flattened[] = [
                            'name' => $key,
                            'contents' => $v
                        ];
                    }
                }
                return $flattened;
            }

            /**
             * Converts the uploaded file instance for guzzle
             * @param   array  $files
             */
            protected function prepareFileArray(array &$files): void{

                foreach ($files as &$item){
                    /** @var \Laminas\Diactoros\UploadedFile $file */
                    $file = $item['contents'];
                    $item['filename'] = $file->getClientFilename();
                    $item['contents'] = $file->getStream();
                    $item['headers'] =[
                        'Content-Type' => $file->getClientMediaType()
                    ];
                }
            }
        };
    }
}
