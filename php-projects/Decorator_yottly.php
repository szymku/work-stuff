<?php

interface RendererInterface{
	public function getOutput();
}


//Entity
class Customer implements RendererInterface
{

    public function getOutput()
    {
        return json_encode(['customer', 'data', 'in', 'json']);
    }
}


//Entity
class Purchase implements RendererInterface
{

    public function getOutput()
    {
        return json_encode(['purchase', 'data', 'in', 'json']);
    }
}



// basic rander of default format (JSON)
class BasicRenderer implements RendererInterface
{
	protected $renderer;

	public function __construct(RendererInterface $renderer)
	{
		$this->renderer = $renderer;
	}
    
    public function getOutput()
    {
    	return $this->renderer->getOutput();
    }
}


//if we need array 
class ArrayRenderer implements RendererInterface
{
	protected $basicRenderer;

	public function __construct(BasicRenderer $basicRenderer)
	{
		$this->basicRenderer = $basicRenderer;
	}
    
    public function getOutput()
    {
    	return json_decode($this->basicRenderer->getOutput());
    }
}


//if we need xml (dummy xml) 
class XMLRenderer implements RendererInterface
{
	protected $basicRenderer;

	public function __construct(BasicRenderer $basicRenderer)
	{
		$this->basicRenderer = $basicRenderer;
	}
    
    public function getOutput()
    {
    	//do xml ;)
    	return '<xml>' . $this->basicRenderer->getOutput() . '</xml>';
    }
}

 

$customer = new Customer();
$purchase = new Purchase();


$defaultRenderer = new BasicRenderer($customer);
$customerOutput = $defaultRenderer->getOutput();

$defaultRenderer = new BasicRenderer($purchase);
$purchaseOutput = $defaultRenderer->getOutput();

$arrayRenderer = new ArrayRenderer(new BasicRenderer($purchase));
$xmlRenderer = new XMLRenderer(new BasicRenderer($purchase));


var_dump($purchaseOutput, $customerOutput); 

// after decoration
var_dump($arrayRenderer->getOutput());
var_dump($xmlRenderer->getOutput());