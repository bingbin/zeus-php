<?php
namespace zeus\http;

use zeus\base\AbstractComponent;
use zeus\mvc\Controller;
use zeus\sandbox\ConfigManager;

/**
 * Created by IntelliJ IDEA.
 * User: nathena
 * Date: 2017/6/2
 * Time: 19:32
 */

class Dispatcher extends AbstractComponent
{
    protected $controller;
    protected $action;
    protected $params = [];

    private $uri;
    private $config;

    private $request;
    private $response;

    public function __construct($uri)
    {
        parent::__construct(false);

        $this->config = ConfigManager::config("router");
        $this->uri = trim($uri,"/");

        $this->route();
    }

    public function forward(Request $request,Response $response){

        $this->request = $request;
        $this->response = $response;

        $this->request->setData($this->params);

        $controller = null;

        try{
            $controller = new $this->controller();
            if( !($controller instanceof Controller) )
            {

            }
            call_user_func_array(array($controller, $router->getMethod()),$data);

        }catch(\Exception $e){

        }

    }

    private function route()
    {
        if( "/" == $this->uri || "" == $this->uri )
        {
            if( $this->routeDefaultController() )
            {
                return;
            }
        }

        if( $this->routerUriRewrite() )
        {
            return;
        }

        if( $this->routeUriPath() )
        {
            return;
        }

        if( $this->config['router.404_override'] )
        {
            $this->routeDefaultController();
        }
    }

    private function routerUriRewrite()
    {
        $rewrite = $this->config['router.rewrite'];

        if( !empty($rewrite) && is_array($rewrite))
        {
            foreach ($rewrite as $pattern => $replacement )
            {
                if( preg_match("#^$pattern$#", $this->uri_path))
                {
                    $rule = preg_replace("#^$pattern$#", $replacement, $this->uri_path);
                    $rule = explode("@", $rule);

                    if( class_exists($rule[0]))//autoload
                    {
                        $this->controller = $rule[0];

                        $rule = explode("#",$rule[1]);
                        $this->action = $rule[0];

                        if( count($rule)>1 )
                        {
                            $this->merge_params(explode(",", $rule[1]));
                        }

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function routeUriPath()
    {
        $rule = explode("/", $this->uri_path);
        $count = count($rule);

        $controller_packpage = $this->config['router.default_controller_ns'].'\\controller';

        $index = 0;
        do
        {
            $controller = $controller_packpage.'\\'.ucfirst($rule[$index]);
            $controller_packpage = $controller_packpage.'\\'.$rule[$index];

            if( class_exists($controller) )
            {
                $this->controller = $controller;

                if( $index+1 < $count)
                {
                    $_rule = array_slice($rule, $index+1);

                    $method = $_rule[0];
                    $_params_index = $index+2;
                    if( method_exists($controller, $method))
                    {
                        $this->action = $_rule[0];
                    }
                    else
                    {
                        $_params_index = $index+1;
                        $this->action = $this->config['router.default_controller_action'];
                    }

                    if( $_params_index < $count)
                    {
                        $this->merge_params( array_slice($rule, $_params_index) );
                    }
                }
                else
                {
                    $this->action = $this->config['router.default_controller_action'];
                }

                return true;
            }

            $index++;
        }
        while($index<$count);

        return false;
    }

    private function routeDefaultController()
    {
        $controller = $this->config['router.default_controller_ns'].'\\controller\\'.$this->config['router.default_controller'];
        if( class_exists($controller) )
        {
            $this->controller = $controller;
            $this->action     = $this->config['router.default_controller_action'];
            $this->merge_params( explode("/", $this->uri_path));
            return true;
        }

        return false;
    }

    private function merge_params(array $params)
    {
        $this->params = array_merge($this->params,$params);
    }
}