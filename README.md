# common-lb

PHP library providing reusable errors and JSON HTTP responses.
Requires PHP 8.4.1 or newer and Symfony HttpFoundation 8.1.

```php
use NuraBiz\Common\Error\Error;
use NuraBiz\Common\Http\Response\ErrorResponse;
use NuraBiz\Common\Http\Response\SuccessResponse;

$success = new SuccessResponse(data: ['id' => 42], message: 'Created', status: 201);

$error = new Error(code: 'invalid_email', message: 'Invalid email', data: ['field' => 'email']);
$failure = new ErrorResponse($error, status: 422);
```

Responses contain `status`, `data`, and `message`. The `status` field matches
the HTTP status code. For `ErrorResponse`, `data` contains the serialized error
(`code`, `message`, `data`), and the response message comes from that error.

`SuccessResponse` defaults to HTTP 200, `ErrorResponse` to HTTP 400.
Both accept custom response headers. Success responses require a 2xx status
that permits a body (204 and 205 are rejected); errors require a 4xx or 5xx status.

This package has no kernel, routes, controllers, or environment configuration.
Return its responses from the consuming application's HTTP handlers.

Install dependencies with `composer install`.

## Docker tests

`NuraBiz\Common\Testing\DockerTestToolExtension` integrates with PHPUnit 13.4.
Install PHPUnit in the consuming project. Docker Engine must be available.

```xml
<extensions>
    <bootstrap class="NuraBiz\Common\Testing\DockerTestToolExtension">
        <parameter name="mysql" value="true" />
        <parameter name="migrations" value="true" />
    </bootstrap>
</extensions>
```

PHPUnit parameters use the `value` attribute. `mysql=true` enables MySQL.
The consuming application's test bootstrap must load its `.env.test` before
extensions bootstrap. The extension requires these environment variables:
`TEST_MYSQL_VERSION`, `TEST_MYSQL_DATABASE`, `TEST_MYSQL_USER`,
`TEST_MYSQL_PASSWORD`, `TEST_MYSQL_ROOT_PASSWORD`, and `TEST_MYSQL_PORT`.
Connection settings have no defaults in this library. Configure the application's
DATABASE_URL from the same variables. The port binds only to 127.0.0.1 and data
lives in tmpfs. The readiness timeout is 180 seconds.
The extension starts MySQL when test execution begins, probes it with SELECT 1,
and removes its unique container on PHP shutdown, including after failures.
Listing tests does not start Docker. Forced process termination (SIGKILL) cannot
run shutdown cleanup.

Optional `migrations=true` runs the consuming project's Doctrine migrations after
MySQL becomes ready, using the current PHP binary and `bin/console
 doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration`.
It defaults to false. The consumer must provide Symfony Console and Doctrine
Migrations; the library does not depend on them.

For custom setup, optional `afterStart` is a JSON array of command arguments, executed once after
MySQL becomes ready, with a 60-second timeout. `@php` resolves to the current PHP
binary. APP_ENV and DATABASE_URL from the test bootstrap are forwarded to it.
Optional `directory` sets the working directory; the default is the current
working directory. Migration files and application configuration belong to the consumer.
Do not combine `migrations=true` with `afterStart`.
