<?php
declare(strict_types=1);

namespace EaseAppPHP\EABlueprint\App\Providers;

use Illuminate\Container\Container;

use \EaseAppPHP\Foundation\ServiceProvider;

use \EaseAppPHP\Other\Log;

//use \Odan\Session\PhpSession;

class RouteServiceProvider extends ServiceProvider
{
    protected $container;
    protected $eaRouterinstance;
    protected $config;
    protected $serverRequest;
    protected $eaRequestConsoleStatusResult;
    protected $routesList;
    protected $routes;
    protected $matchedRouteResponse;
    private $middlewarePipeQueue;
    protected $middlewarePipeQueueEntries;
    private $constructedResponse = [];
	protected $session;
	protected $baseWebResponse;
    
    /**
     * Create a new Illuminate application instance.
     *
     * @param  string|null  $basePath
     * @return void
     */
    public function __construct($container)
    {
        $this->container = $container;
    }   
    
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->container->get('EARequestConsoleStatusResult') == "Web") {
            
            $eaRouter = new \EARouter\EARouter();
            $this->container->instance('\EARouter\EARouter', $eaRouter);
        
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->container->get('EARequestConsoleStatusResult') == "Web") {
            
            $this->eaRouterinstance = $this->container->get('\EARouter\EARouter');
        
            $this->config = $this->container->get('config');
            $this->serverRequest = $this->container->get('\Laminas\Diactoros\ServerRequestFactory');
			
			//Get Routes from /routes folder w.r.t. web, ajax, ajax-web-service-common, rest-api, soap-api related files. This scenario excludes CLI and Channels primarily.
            $this->routes = $this->eaRouterinstance->getFromFilepathsArray($this->config["mainconfig"]["routing_engine_rule_files"]);
            //var_dump($this->routes);
            $this->container->instance('routes', $this->routes);
            $this->routesList = $this->container->get('routes');
        
            //Match Route			
            $this->matchedRouteResponse = $this->eaRouterinstance->matchRoute($this->routes, $this->serverRequest->getUri()->getPath(), $this->serverRequest->getQueryParams(), $this->serverRequest->getMethod(), $this->config["mainconfig"]["routing_rule_length"]);
            
			$this->container->instance('matchedRouteResponse', $this->matchedRouteResponse);
                  
			$matchedRouteKey = $this->container->get('matchedRouteResponse')["matched_route_key"];
			
			$this->container->instance('MatchedRouteKey', $matchedRouteKey);
			$this->matchedRouteKey = $this->container->get('MatchedRouteKey'); 
			//echo "matched route key (before mutation, in RouteServiceProvider): " . $this->matchedRouteKey . "<br>";
			$matchedRouteDetails = $this->routesList[$this->matchedRouteKey];
			
			if ($matchedRouteKey == "header-response-only-405-method-not-allowed") {
				
				$matchedRouteDetails["allowed_request_methods"] = $this->matchedRouteResponse["allowed_request_methods"];
				
			}
			
			$this->container->instance('MatchedRouteDetails', $matchedRouteDetails);
			$this->matchedRouteDetails = $this->container->get('MatchedRouteDetails'); 
			
			$requiredRouteType = "";
			$requiredRouteType = $this->matchedRouteDetails["route_type"];
			$requiredWithMiddleware = $this->matchedRouteDetails["with_middleware"];
			$requiredWithoutMiddleware = $this->matchedRouteDetails["without_middleware"];
			if($requiredWithMiddleware != ""){
				$pageWithMiddlewareArray = explode(",", $requiredWithMiddleware);
			}
			if($requiredWithoutMiddleware != ""){
				$pageWithoutMiddlewareArray = explode(",", $requiredWithoutMiddleware);
			}
			
			if ((($requiredRouteType == "ajax") || ($requiredRouteType == "soap-web-service") || ($requiredRouteType == "rest-web-service") || ($requiredRouteType == "ajax-web-service-common")) && ($this->serverRequest->getServerParams()['APP_DEBUG'] == "true")) {

				$whoopsHandler = $this->container->get('\Whoops\Run');
				$whoopsHandler->pushHandler(new \Whoops\Handler\XmlResponseHandler());
				$whoopsHandler->pushHandler(new \Whoops\Handler\JsonResponseHandler());
				$whoopsHandler->register();

			}
			
			//throw new \RuntimeException("Oopsie!");
			
			if($requiredRouteType != "" && array_key_exists($requiredRouteType, $this->config["mainconfig"]["route_type_middleware_group_mapping"])){
                $requiredRouteTypeMiddlewareGroupMappingValue = $this->config["mainconfig"]["route_type_middleware_group_mapping"][$requiredRouteType];
				//echo "requiredRouteTypeMiddlewareGroupMappingValue: " . $requiredRouteTypeMiddlewareGroupMappingValue . "<br>\n";
            }
			
			$this->baseWebResponse = $this->container->get('\EaseAppPHP\Foundation\BaseWebResponse');
			
			// Step 1: Do something first
			$appClassData = [
					'container' => $this->container,
					'config' => $this->config,
					'routes' => $this->routes,
					'eaRouterinstance' => $this->eaRouterinstance,
					'matchedRouteResponse' => $this->matchedRouteResponse,
					'matchedRouteKey' => $this->matchedRouteKey,
					'matchedRouteDetails' => $this->matchedRouteDetails,
					'baseWebResponse' => $this->baseWebResponse,
			];
			
	/* 		'session_based_authentication' => '1',
	'active_session_backend' => env('SESSION_DRIVER', 'file'),
	'files_based_session_storage_location_choice' => env('SESSION_STORAGE_LOCATION_SETTING', 'custom-location'),
	'files_based_session_storage_custom_path' => env('APP_BASE_PATH') . 'sessions',
	
	'single_redis_server_session_backend_host' => 'tcp://localhost:6379',
	'session_lifetime' => env('SESSION_LIFETIME', '86400'),
	 */
	
			
			if (($requiredRouteType == "frontend-web-app") || ($requiredRouteType == "backend-web-app") || ($requiredRouteType == "web-app-common") || ($requiredRouteType == "ajax") || ($requiredRouteType == "ajax-web-service-common")) {

				if ($this->container->has('\Odan\Session\PhpSession') === true) {
			
					//Get the instance of \Odan\Session\PhpSession
					$this->session = $this->container->get('\Odan\Session\PhpSession');
					
					$appClassData["session"] = $this->session;
					
				} else {
					//throw https://www.php-fig.org/psr/psr-11/#not-found-exception exception
				}
				
			}
            
            //Define Laminas Stratigility Middlewarepipe
            $middlewarePipe = new \Laminas\Stratigility\MiddlewarePipe();  // API middleware collection
            $this->container->instance('\Laminas\Stratigility\MiddlewarePipe', $middlewarePipe);
            $this->middlewarePipeQueue = $this->container->get('\Laminas\Stratigility\MiddlewarePipe');
            
            //Default Whoops based Error Handler using Whoops Middleware
            //$this->middlewarePipeQueue->pipe(new \Franzl\Middleware\Whoops\WhoopsMiddleware);
            
            //Middleware is expected to pass on the details as attributes of serverRequest to the next middleware
            $this->middlewarePipeQueue->pipe(new \EaseAppPHP\EABlueprint\App\Http\Middleware\PassingAppClassDataToMiddleware($appClassData));
            
            foreach ($this->config["middleware"]["middleware"] as $singleGlobalMiddlewareRowKey => $singleGlobalMiddlewareRowValue) {
                
				if(!in_array($singleGlobalMiddlewareRowValue, $this->constructedResponse)){
					$this->constructedResponse[] = $singleGlobalMiddlewareRowValue;
				}
                
            }
            
            foreach ($this->config["middleware"]["middlewareGroups"] as $singleMiddlewareGroupRowKey => $singleMiddlewareGroupRowValue) {
                //echo "requiredRouteTypeMiddlewareGroupMappingValue: " . $requiredRouteTypeMiddlewareGroupMappingValue . "<br>\n";
				//echo "singleMiddlewareGroupRowKey: " . $singleMiddlewareGroupRowKey . "<br>\n";
                $expectedMiddlewareGroupsList = array("web", "api", "ajax");
                if (($requiredRouteTypeMiddlewareGroupMappingValue == $singleMiddlewareGroupRowKey) && (in_array($singleMiddlewareGroupRowKey, $expectedMiddlewareGroupsList))) {
                    
					foreach($singleMiddlewareGroupRowValue as $singleMiddlewareGroupRowValueEntry){
					   
						if(!in_array($singleMiddlewareGroupRowValueEntry, $this->constructedResponse)){
							$this->constructedResponse[] = $singleMiddlewareGroupRowValueEntry;
							//echo "singleMiddlewareGroupRowValueEntry: " . $singleMiddlewareGroupRowValueEntry . "<br>\n";
						}
					   
					}
                    break;
                }
              
            }
            
			if(isset($requiredWithMiddlewareArray)){
                foreach($requiredWithMiddlewareArray as $requiredWithMiddlewareArrayEntry){
                    
                    foreach($this->config["middleware"]["routeMiddleware"] as $singlerouteMiddlewareKey => $singlerouteMiddlewareValue){

                        if($requiredWithMiddlewareArrayEntry == $singlerouteMiddlewareKey){
                            
                            if(!isset($this->constructedResponse[$requiredWithMiddlewareArrayEntry])){
                                $this->constructedResponse[] = $singlerouteMiddlewareValue;
                            }
                            
                        }

                    }
                }
            }
            if(isset($requiredWithoutMiddlewareArray)){
                foreach($requiredWithoutMiddlewareArray as $requiredWithoutMiddlewareArrayEntry){
                    foreach($this->config["middleware"]["routeMiddleware"] as $singlerouteMiddlewareKey => $singlerouteMiddlewareValue){

                        if($requiredWithoutMiddlewareArrayEntry == $singlerouteMiddlewareKey){
                            
                            if(isset($this->constructedResponse[$requiredWithoutMiddlewareArrayEntry])){
                                unset($this->constructedResponse[$requiredWithoutMiddlewareArrayEntry]);
                                //echo "middleware removed";
                                
                                //ISSUE TO BE FIXED
                            }
                            
                        }

                    }
                    
                }
            }
            
			
			//Get the instance of \Odan\Session\PhpSession
			//$this->session = $this->container->get('\Odan\Session\PhpSession');
            
            foreach ($this->constructedResponse as $constructedResponseRowKey => $constructedResponseRowValue) 
			{
                //To provide input to constructor for SessionMiddleware
				if ($constructedResponseRowValue == "Odan\Session\Middleware\SessionMiddleware") {
					
					//$this->middlewarePipeQueue->pipe(new $constructedResponseRowValue($this->session));
					
					if (($requiredRouteType == "frontend-web-app") || ($requiredRouteType == "backend-web-app") || ($requiredRouteType == "web-app-common") || ($requiredRouteType == "ajax") || ($requiredRouteType == "ajax-web-service-common")) {

						if ($this->container->has('\Odan\Session\PhpSession') === true) {
						
							$this->middlewarePipeQueue->pipe(new $constructedResponseRowValue($this->session));
							
						} else {
							//throw https://www.php-fig.org/psr/psr-11/#not-found-exception exception
						}

					} else {
						
						throw new \Exception("Sessions will be enabled only for ajax and web requests. Do remove Session Middleware otherwise.");
						
					}
					
				} else {
					
					$this->middlewarePipeQueue->pipe(new $constructedResponseRowValue());
					
				}
                //$this->middlewarePipeQueue->pipe(new $constructedResponseRowValue());
                
            }
			/* // Set session value
			$this->session->set('bar', 'foo');

			
			$this->session->set('Srirama', 'Namaskaram Srirama');
			
			// Set session value
			$this->session->set('bar1', 'foo1');

			
			$this->session->set('Srirama1', 'Namaskaram1'); */
			
			
			
			/* echo $this->session->get('Srirama');
			echo "<br>";
			echo $this->session->get('Srirama1');
			echo "<br>";
			echo $this->session->get('bar');
			 exit; */

			// Commit and close the session
			//$this->session->save();
			
            /*
             * FEATURES POSTPONED w.r.t. IMPLEMENTATION middleware priority, adding/removing specific middleware to/from a ROUTE, postponing these two features andi for now
             * skip middleware parameters as we tried using attributes concept of server request. need to load list of middleware that i sloaded and checks to be made when loading other middleware to prevent duplication.
             * Before and after middleware logic along with terminable middleware logic to be considered for middleware implementation
             */
            
            //Laminas 404 Not Found Handler. The namespace to be changed later to Laminas\Stratigility\Handler\NotFoundHandler
            $this->middlewarePipeQueue->pipe(new \Laminas\Stratigility\Middleware\NotFoundHandler(function () {
				return new \Laminas\Diactoros\Response();
			}));
			
            //Assign MiddlewarePipe entries into container
            $this->container->instance('middlewarePipeQueueEntries', $this->middlewarePipeQueue);
            
            
        }
        
            
        
    }
    
}