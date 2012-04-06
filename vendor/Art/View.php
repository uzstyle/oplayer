<?php

namespace Art;

class View {
    private $app  = null;
    
    public function __construct($app) {
        $this->app = $app;
    }

    public function render( $layout, $template, $vars = array() ) {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH'];
        $path = ROOT . '/view';
        
        require_once ROOT . '/helpers.php';
        foreach ($vars as $key => $value) { $$key = $value; }
        $app = $this->app;
        ob_start();

        if ( file_exists(ROOT . '/personalization/' . $template) ) {
            require_once ROOT . '/personalization/' . $template;
        } else {
            require_once $path . '/' . $template;
        }
        

        $content = ob_get_clean();
        
        if ( null == $layout || $isAjax ) {
            return $content;
        }
        
        ob_start();
        require_once $path . '/' . $layout;
        $html = ob_get_clean();
        
        return $html;
    }
    
}