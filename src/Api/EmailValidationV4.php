<?php

declare(strict_types=1);

/*
 * Copyright (C) 2013 Mailgun
 *
 * This software may be modified and distributed under the terms
 * of the MIT license. See the LICENSE file for details.
 */

namespace Mailgun\Api;

use Exception;
use Mailgun\Assert;
use Mailgun\Exception\InvalidArgumentException;
use Mailgun\Model\EmailValidationV4\CreateBulkJobResponse;
use Mailgun\Model\EmailValidationV4\CreateBulkPreviewResponse;
use Mailgun\Model\EmailValidationV4\DeleteBulkJobResponse;
use Mailgun\Model\EmailValidationV4\GetBulkJobResponse;
use Mailgun\Model\EmailValidationV4\GetBulkJobsResponse;
use Mailgun\Model\EmailValidationV4\GetBulkPreviewResponse;
use Mailgun\Model\EmailValidationV4\GetBulkPreviewsResponse;
use Mailgun\Model\EmailValidationV4\PromoteBulkPreviewResponse;
use Mailgun\Model\EmailValidationV4\ValidateResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * @see https://documentation.mailgun.com/en/latest/api-email-validation.html
 */
class EmailValidationV4 extends HttpApi
{
    /**
     * Addresses are validated based off defined checks.
     *
     * @param string $address An email address to validate. Maximum: 512 characters.
     *
     * @return ValidateResponse|ResponseInterface
     *
     * @throws Exception Thrown when we don't catch a Client or Server side Exception
     */
    public function validate(string $address, bool $providerLookup = true)
    {
        Assert::stringNotEmpty($address);

        $params = [
            'address' => $address,
            'provider_lookup' => $providerLookup,
        ];

        $response = $this->httpGet('/v4/address/validate', $params);

        return $this->hydrateResponse($response, ValidateResponse::class);
    }

    /**
     * @param mixed $filePath - file path or file content
     *
     * @return mixed|ResponseInterface
     *
     * @throws Exception
     */
    public function createBulkJob(string $listId, $filePath)
    {
        Assert::stringNotEmpty($listId);

        if (strlen($filePath) < PHP_MAXPATHLEN && is_file($filePath)) {
            $fileData = ['filePath' => $filePath];
        } else {
            $fileData = [
                'fileContent' => $filePath,
                'filename' => 'file',
            ];
        }

        $postDataMultipart = [];
        $postDataMultipart[] = $this->prepareFile('file', $fileData);

        $response = $this->httpPostRaw(sprintf('/v4/address/validate/bulk/%s', $listId), $postDataMultipart);
        $this->closeResources($postDataMultipart);

        return $this->hydrateResponse($response, CreateBulkJobResponse::class);
    }

    /**
     * @param array $filePath ['fileContent' => 'content'] or ['filePath' => '/foo/bar']
     */
    private function prepareFile(string $fieldName, array $filePath): array
    {
        $filename = isset($filePath['filename']) ? $filePath['filename'] : null;

        if (isset($filePath['fileContent'])) {
            // File from memory
            $resource = fopen('php://temp', 'r+');
            fwrite($resource, $filePath['fileContent']);
            rewind($resource);
        } elseif (isset($filePath['filePath'])) {
            // File form path
            $path = $filePath['filePath'];

            // Remove leading @ symbol
            if (0 === strpos($path, '@')) {
                $path = substr($path, 1);
            }

            $resource = fopen($path, 'r');
        } else {
            throw new InvalidArgumentException('When using a file you need to specify parameter "fileContent" or "filePath"');
        }

        return [
            'name' => $fieldName,
            'content' => $resource,
            'filename' => $filename,
        ];
    }

    /**
     * Close open resources.
     */
    private function closeResources(array $params): void
    {
        foreach ($params as $param) {
            if (is_array($param) && array_key_exists('content', $param) && is_resource($param['content'])) {
                fclose($param['content']);
            }
        }
    }

    /**
     * @return DeleteBulkJobResponse|ResponseInterface
     *
     * @throws Exception
     */
    public function deleteBulkJob(string $listId)
    {
        Assert::stringNotEmpty($listId);

        $response = $this->httpDelete(sprintf('/v4/address/validate/bulk/%s', $listId));

        return $this->hydrateResponse($response, DeleteBulkJobResponse::class);
    }

    /**
     * @return GetBulkJobResponse|ResponseInterface
     *
     * @throws Exception
     */
    public function getBulkJob(string $listId)
    {
        Assert::stringNotEmpty($listId);

        $response = $this->httpGet(sprintf('/v4/address/validate/bulk/%s', $listId));

        return $this->hydrateResponse($response, GetBulkJobResponse::class);
    }

    /**
     * @return GetBulkJobsResponse|ResponseInterface
     *
     * @throws Exception
     */
    public function getBulkJobs(int $limit = 500)
    {
        Assert::integer($limit);
        Assert::greaterThan($limit, 0);

        $response = $this->httpGet('/v4/address/validate/bulk', [
            'limit' => $limit,
        ]);

        return $this->hydrateResponse($response, GetBulkJobsResponse::class);
    }

    public function getBulkPreviews(int $limit = 500)
    {
        Assert::integer($limit);
        Assert::greaterThan($limit, 0);

        $response = $this->httpGet('/v4/address/validate/preview', [
            'limit' => $limit,
        ]);

        return $this->hydrateResponse($response, GetBulkPreviewsResponse::class);
    }

    /**
     * @return mixed|ResponseInterface
     *
     * @throws Exception
     */
    public function createBulkPreview(string $previewId, $filePath)
    {
        Assert::stringNotEmpty($previewId);

        if (strlen($filePath) < PHP_MAXPATHLEN && is_file($filePath)) {
            $fileData = ['filePath' => $filePath];
        } else {
            $fileData = [
                'fileContent' => $filePath,
                'filename' => 'file',
            ];
        }

        $postDataMultipart = [];
        $postDataMultipart[] = $this->prepareFile('file', $fileData);

        $response = $this->httpPostRaw(sprintf('/v4/address/validate/preview/%s', $previewId), $postDataMultipart);
        $this->closeResources($postDataMultipart);

        return $this->hydrateResponse($response, CreateBulkPreviewResponse::class);
    }

    /**
     * @return mixed|ResponseInterface
     *
     * @throws Exception
     */
    public function getBulkPreview(string $previewId)
    {
        Assert::stringNotEmpty($previewId);

        $response = $this->httpGet(sprintf('/v4/address/validate/preview/%s', $previewId));

        return $this->hydrateResponse($response, GetBulkPreviewResponse::class);
    }

    /**
     * @return bool
     */
    public function deleteBulkPreview(string $previewId)
    {
        Assert::stringNotEmpty($previewId);

        $response = $this->httpDelete(sprintf('/v4/address/validate/preview/%s', $previewId));

        return 204 === $response->getStatusCode();
    }

    /**
     * @return mixed|ResponseInterface
     *
     * @throws Exception
     */
    public function promoteBulkPreview(string $previewId)
    {
        Assert::stringNotEmpty($previewId);

        $response = $this->httpPut(sprintf('/v4/address/validate/preview/%s', $previewId));

        return $this->hydrateResponse($response, PromoteBulkPreviewResponse::class);
    }
}
