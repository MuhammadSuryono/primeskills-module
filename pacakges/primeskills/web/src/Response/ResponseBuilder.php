<?php
/*
 * Copyright (c) 2023.
 * Created At: 1/20/23, 9:52 AM
 * Created By: Muhammad Suryono
 */

namespace Primeskills\Web\Response;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Primeskills\Web\Exceptions\PrimeskillsException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Primeskills\Web\Traits\PrimeskillsLog;


/**
 *
 */
class ResponseBuilder
{
    use PrimeskillsLog;
    /**
     * @var int
     */
    private $code;
    /**
     * @var string
     */
    private $message;
    /**
     * @var null
     */
    private $data;
    /**
     * @var int
     */
    private $status;
    /**
     * @var Exception
     */
    private $exception;
    /**
     * @var array
     */
    private $errors;

    /**
     * @var bool
     */
    private $success;

    /**
     * @var string
     */
    private $errorCode;

    /**
     *
     */
    public function __construct()
    {
        $this->code = 200;
        $this->message = 'success';
        $this->data = null;
        $this->status = 200;
        $this->success = true;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array $errors
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @param int $code
     * @return $this
     */
    public function setCode(int $code): ResponseBuilder
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): ResponseBuilder
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function setData($data): ResponseBuilder
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param bool $status
     * @return $this
     */
    public function setStatus(bool $status): ResponseBuilder
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @param Throwable $throwable
     * @return $this
     */
    public function instanceException(Throwable $throwable): ResponseBuilder
    {
        $this->exception = Response::builder()->checkTypeException($throwable);
        $this->exceptionHandle();
        return $this;
    }

    /**
     * @return array
     */
    public function build(): array
    {
        if ($this->errorCode != null) {
            $this->message = "Code [$this->errorCode]. $this->message";
        }
        return [
            'success' => $this->code >= 200 && $this->code < 300,
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data
        ];
    }

    /**
     * @return JsonResponse
     */
    public function buildJson(): JsonResponse
    {
        return response()->json($this->build(), $this->code);
    }

    /**
     * @return void
     */
    private function exceptionHandle()
    {
        $statusCode = 500;
        if (method_exists($this->exception, 'getStatusCode')) {
            $statusCode = $this->exception->getStatusCode();
        }

        $this->setCode($statusCode);
        $this->mapMessageDefaultStatusCode($statusCode);
        if (config('app.debug')) {
            $this->errors['trace'] = $this->exception->getTrace();
            $this->errors['code'] = $this->exception->getCode();
        }

        $this->setData($this->errors);
    }

    /**
     * @param int $statusCode
     * @return void
     */
    private function mapMessageDefaultStatusCode(int $statusCode)
    {
        $this->write()->error("Error Code [$statusCode] " . $this->exception->getMessage());
        switch ($statusCode) {
            case 400:
                $this->setMessage($this->exception->getMessage());
                break;
            case 401:
                $this->setMessage($this->exception->getMessage() == null ? 'Unauthorized' : $this->exception->getMessage());
                break;
            case 403:
                $this->setMessage('Forbidden');
                break;
            case 404:
                $this->setMessage('Not Found');
                break;
            case 405:
                $this->setMessage('Method Not Allowed');
                break;
            case 422:
                $this->setMessage($this->exception->getMessage());
                $this->errors = $this->exception->getData();
                break;
            default:
                $message = strpos(strtolower($this->exception->getMessage()), 'sql') !== false ? 'Whoops, looks like something went wrong' : $this->exception->getMessage();
                $this->setMessage(($statusCode == 500) ? $message : $this->exception->getMessage());
                break;
        }
    }

    /**
     * @param Exception $exception
     * @return ResponseBuilder
     */
    public function setException(Exception $exception) :ResponseBuilder
    {
        $this->exception = $exception;
        return $this;
    }

    /**
     * @param Throwable $e
     * @return Throwable|AccessDeniedHttpException|HttpException|NotFoundHttpException
     */
    public function checkTypeException(Throwable $e)
    {
        if ($e instanceof ModelNotFoundException) {
            $e = new NotFoundHttpException($e->getMessage(), $e);
        } elseif ($e instanceof AuthorizationException) {
            $e = new AccessDeniedHttpException($e->getMessage(), $e);
        } elseif ($e instanceof TokenMismatchException) {
            $e = new HttpException(419, $e->getMessage(), $e);
        } elseif ($e instanceof SuspiciousOperationException) {
            $e = new NotFoundHttpException('Bad hostname provided.', $e);
        } elseif ($e instanceof AuthenticationException) {
            $e = new HttpException(401, $e->getMessage());
        } elseif ($e instanceof ValidationException) {
            $e = new PrimeskillsException($e->status, $e->getMessage(), [
                'errors' => $e->errors(),
            ]);
        } elseif ($e instanceof BadRequestException) {
            $e = new HttpException(400, $e->getMessage());
        }

        return $e;
    }

    /**
     * @return $this
     */
    public function version(): ResponseBuilder
    {
        $this->setData(['version' => env('APP_VERSION', '1.0')])
            ->setMessage('Success get service ' . env('APP_NAME'));
        return $this;
    }

}
