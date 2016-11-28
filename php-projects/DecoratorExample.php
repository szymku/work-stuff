<?php


interface CarService{
	public function getCost();
}

class BasicInspection implements CarService
{
    
    public function getCost()
    {
    	return 20;
    }
}

class OilChange implements CarService
{
	protected $carService;
    

     public function __construct(CarService $carService)
     {
     	$this->carService= $carService;
     }


    public function getCost()
    {
    	return 15  + $this->carService->getCost();
    }
}


$cost = (new OilChange(new BasicInspection))->getCost();

var_dump($cost);