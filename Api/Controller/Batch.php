<?php

namespace Truonglv\Api\Api\Controller;

use XF\Http\Request;
use XF\Api\Mvc\Dispatcher;
use XF\Api\Controller\AbstractController;

class Batch extends AbstractController
{
    public function actionPost()
    {
        $input = (string) \file_get_contents('php://input');
        $json = \json_decode($input, true);

        if (!\is_array($json)) {
            return $this->apiError('Invalid batch json format', 'invalid_batch_json_format');
        }

        $results = [];

        foreach ($json as $batchRequest) {
            $batchRequest = \array_replace($this->getDefaultBatchRequest(), $batchRequest);

            $results[$batchRequest['uri']] = $this->runInternalRequest($batchRequest);
        }

        return $this->apiResult($results);
    }

    /**
     * @param array $batch
     * @return array
     */
    protected function runInternalRequest(array $batch)
    {
        if (!isset($batch['uri'])) {
            return null;
        }

        $server = \array_replace($_SERVER, [
            'REQUEST_METHOD' => \strtoupper($batch['method'])
        ]);

        $request = new Request($this->app()->inputFilterer(), $batch['params'], [], [], $server);
        $dispatcher = new Dispatcher($this->app(), $request);

        $match = $dispatcher->route($batch['uri']);
        $reply = $dispatcher->dispatchLoop($match);

        $response = $dispatcher->render($reply, 'api');

        return (array) \json_decode($response->body(), true);
    }

    /**
     * @return array
     */
    protected function getDefaultBatchRequest()
    {
        return [
            'method' => 'GET',
            'uri' => null,
            'params' => []
        ];
    }
}
