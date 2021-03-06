<?php

namespace Puzzle\AMQP\Workers\Providers;

use Puzzle\AMQP\Workers\WorkerProvider;
use Puzzle\AMQP\Workers\WorkerContext;
use Puzzle\AMQP\Collections\MessageHookCollection;

class Pimple implements WorkerProvider
{
    const
        MESSAGE_HOOKS_DIC_KEY = 'amqp.message.hooks';
    
    private
        $container;

    public function __construct(\Pimple $container)
    {
        $this->container = $container;
    }

    public function getWorker($workerName)
    {
        $workerContext = null;
        $key = $this->computeWorkerServiceKey($workerName);

        if(isset($this->container[$key]))
        {
            $workerContext = $this->container[$key];
        }
        
        if(isset($this->container[self::MESSAGE_HOOKS_DIC_KEY]))
        {
            $workerContext->setMessageHooks($this->container[self::MESSAGE_HOOKS_DIC_KEY]);
        }

        return $workerContext;
    }

    private function computeWorkerServiceKey($workerName)
    {
        return sprintf(
            'worker.%s',
            $workerName
        );
    }

    public function listAll()
    {
        $workers = $this->extractWorkers();
        
        return $this->listWorkers($workers);
    }
    
    public function listWithRegexFilter($workerNamePattern)
    {
        $workers = $this->extractWorkers();
        $workers = new \RegexIterator(new \ArrayIterator($workers), sprintf('~^worker\.%s~', $workerNamePattern));
        $workers = iterator_to_array($workers);

        return $this->listWorkers($workers);
    }
    
    private function listWorkers(array $extractedWorkers)
    {
        $workers = array();
        
        foreach($extractedWorkers as $worker)
        {
            $key = $this->formatWorkerName($worker);
            $worker = $this->container[$worker];
        
            if($worker instanceof WorkerContext)
            {
                $workers[$key] = [
                    'queue' => $worker->getQueue(),
                    'description' => $worker->getDescription(),
                    'instances' => $worker->getInstances(),
                    'servers' => $worker->getServers(),
                    'isDeploymentAllowed' => $worker->isDeploymentAllowed(),
                ];
            }
        }
        
        return $workers;
    }

    private function extractWorkers()
    {
        $services = new \ArrayIterator($this->container->keys());
        $services = new \RegexIterator($services, '~^worker\..+~');

        return iterator_to_array($services);
    }

    private function formatWorkerName($workerServiceName)
    {
        return str_replace('worker.', '', $workerServiceName);
    }
}
