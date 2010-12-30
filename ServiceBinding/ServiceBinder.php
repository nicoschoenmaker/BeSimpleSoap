<?php
/*
 * This file is part of the WebServiceBundle.
 *
 * (c) Christian Kerl <christian-kerl@web.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Bundle\WebServiceBundle\ServiceBinding;

use Bundle\WebServiceBundle\ServiceDefinition\Type;

use Bundle\WebServiceBundle\Soap\SoapHeader;

use Bundle\WebServiceBundle\ServiceDefinition\ServiceDefinition;
use Bundle\WebServiceBundle\ServiceDefinition\Header;
use Bundle\WebServiceBundle\ServiceDefinition\Dumper\DumperInterface;
use Bundle\WebServiceBundle\ServiceDefinition\Loader\LoaderInterface;

class ServiceBinder
{
    /**
     * @var \Bundle\WebServiceBundle\ServiceDefinition\ServiceDefinition
     */
    private $definition;

    /**
     * @var \Bundle\WebServiceBundle\ServiceDefinition\Dumper\DumperInterface
     */
    private $definitionDumper;

    /**
     * @var \Bundle\WebServiceBundle\ServiceBinding\MessageBinderInterface
     */
    private $requestMessageBinder;

    /**
     * @var \Bundle\WebServiceBundle\ServiceBinding\MessageBinderInterface
     */
    private $responseMessageBinder;

    public function __construct(
        ServiceDefinition $definition,
        LoaderInterface $loader,
        DumperInterface $dumper,
        MessageBinderInterface $requestMessageBinder,
        MessageBinderInterface $responseMessageBinder
    )
    {
        $loader->loadServiceDefinition($definition);

        $this->definition = $definition;
        $this->definitionDumper = $dumper;

        $this->requestMessageBinder = $requestMessageBinder;
        $this->responseMessageBinder = $responseMessageBinder;
    }

    public function getSerializedServiceDefinition()
    {
        // TODO: add caching
        return $this->definitionDumper->dumpServiceDefinition($this->definition);
    }

    public function getSoapServerClassmap()
    {
        $result = array();

        foreach($this->definition->getHeaders() as $header)
        {
            $this->addSoapServerClassmapEntry($result, $header->getType());
        }

        foreach($this->definition->getMethods() as $method)
        {
            foreach($method->getArguments() as $arg)
            {
                $this->addSoapServerClassmapEntry($result, $arg->getType());
            }
        }

        return $result;
    }

    private function addSoapServerClassmapEntry(&$classmap, Type $type)
    {
        list($namespace, $xmlType) = $this->parsePackedQName($type->getXmlType());
        $phpType = $type->getPhpType();

        if(isset($classmap[$xmlType]) && $classmap[$xmlType] != $phpType)
        {
            // log warning
        }

        $classmap[$xmlType] = $phpType;
    }

    public function isServiceHeader($name)
    {
        return $this->definition->getHeaders()->has($name);
    }

    public function isServiceMethod($name)
    {
        return $this->definition->getMethods()->has($name);
    }

    public function processServiceHeader($name, $data)
    {
        $headerDefinition = $this->definition->getHeaders()->get($name);

        return $this->createSoapHeader($headerDefinition, $data);
    }

    public function processServiceMethodArguments($name, $arguments)
    {
        $methodDefinition = $this->definition->getMethods()->get($name);

        $result = array();
        $result['_controller'] = $methodDefinition->getController();
        $result = array_merge($result, $this->requestMessageBinder->processMessage($methodDefinition, $arguments));

        return $result;
    }

    public function processServiceMethodReturnValue($name, $return)
    {
        $methodDefinition = $this->definition->getMethods()->get($name);

        return $this->responseMessageBinder->processMessage($methodDefinition, $return);
    }

    protected function createSoapHeader(Header $headerDefinition, $data)
    {
        list($namespace, $name) = $this->parsePackedQName($headerDefinition->getType()->getXmlType());

        return new SoapHeader($namespace, $name, $data);
    }

    private function parsePackedQName($qname)
    {
        if(!preg_match('/^\{(.+)\}(.+)$/', $qname, $matches))
        {
            throw new \InvalidArgumentException();
        }

        return array($matches[1], $matches[2]);
    }
}