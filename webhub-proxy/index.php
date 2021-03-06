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
 * Last modified: 2020.08.14 at 17:16
 */

declare(strict_types=1);

use HuR\WebhubProxy\Proxy;

// Include the autoloader -> @todo adjust this path to match your setup!
$VENDOR_DIR = dirname(__DIR__) . '/vendor';
require $VENDOR_DIR . '/autoload.php';

// Run the proxy
(new Proxy(__DIR__))->run();
