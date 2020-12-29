<?php

namespace Truonglv\Api\Api\Controller;

use XF\Http\Request;
use XF\Mvc\Dispatcher;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Message;
use XF\Mvc\Reply\Exception;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Api\Controller\AbstractController;

class Batch extends AbstractController
{
    public function actionPost()
    {
        $input = $this->request()->getInputRaw();
        $jobs = \json_decode($input, true);

        if (!\is_array($jobs)) {
            return $this->apiError('Invalid batch json format', 'invalid_batch_json_format');
        }

        $jobResults = [];
        $start = microtime(true);

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $job = \array_replace_recursive($this->getDefaultJobOptions(), $job);
            $jobResults[$job['uri']] = $this->runJob($job);
        }

        return $this->apiResult([
            'jobs' => $jobResults,
            '_job_timing' => microtime(true) - $start,
        ]);
    }

    /**
     * @param array $job
     * @return array|null
     */
    protected function runJob(array $job): ?array
    {
        if (!isset($job['uri'])) {
            return null;
        }

        $server = \array_replace($_SERVER, [
            'REQUEST_METHOD' => \strtoupper($job['method'])
        ]);

        $request = new Request($this->app()->inputFilterer(), $job['params'], [], [], $server);
        $request->set('_isApiJob', true);
        $dispatcher = new Dispatcher($this->app(), $request);

        $match = $dispatcher->route($job['uri']);
        $reply = $dispatcher->dispatchLoop($match);

        if ($reply instanceof ApiResult) {
            return [
                '_job_result' => 'ok',
                '_job_response' => $reply->getApiResult(),
            ];
        } elseif ($reply instanceof Error) {
            return [
                '_job_result' => 'error',
                '_job_error' => $reply->getErrors(),
            ];
        } elseif ($reply instanceof Message) {
            return [
                '_job_result' => 'ok',
                '_job_message' => $reply->getMessage(),
            ];
        } elseif ($reply instanceof Exception) {
            return [
                '_job_result' => 'error',
                '_job_error' => $reply->getMessage(),
            ];
        }

        throw new \Exception('Unknown reply (' . get_class($reply) . ') occurred.');
    }

    /**
     * @return array
     */
    protected function getDefaultJobOptions(): array
    {
        return [
            'method' => 'GET',
            'uri' => null,
            'params' => []
        ];
    }
}
