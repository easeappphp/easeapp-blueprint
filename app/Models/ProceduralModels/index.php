<?php
//$data = array("name"=>"srirama","place"=>"ayodhya","yuga"=>"Tretayuga");
$data = new StdClass;
$data->name = "srirama";
$data->place = "ayodhya";
$data->yuga = "Tretayuga";
$data->routeRelTemplateContext = "backend";
$data->routeRelTemplateFolderPathPrefix = "/home/blueprint-easeapp-dev/webapps/app-blueprint-dev/public/templates/default-frontend";
$this->session->set('home_area', 'yellareddyguda, Hyderabad');
$data->home_area = $this->container->get('\Odan\Session\PhpSession')->get('home_area');
/* $this->processedModelResponse->name = "srirama";
$this->processedModelResponse->place = "ayodhya";
$this->processedModelResponse->x = "10";
$this->processedModelResponse->colors = array("red", "green", "blue", "yellow");

$this->processedModelResponse->routeRelTemplateContext = $this->getRouteRelTemplateContext();
$this->processedModelResponse->routeRelTemplateFolderPathPrefix = $this->getRouteRelTemplateFolderPathPrefix();
return $this->processedModelResponse; */
?>