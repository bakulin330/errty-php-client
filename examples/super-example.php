<?php
include_once '../src/client.php';
\ErrorCatcher::config('http://errors_catcher.dev', 'u4cgJ267cZjt5RSwiTb2Eh4tlJUa767qflUhTSoi', 'u122', ['email' => '1234']);
\ErrorCatcher::setErrorHandler();

function test1(){
    test2();
}

for($i=0;$i<2000;$i++){
    try{
        throw new \Exception('error №'.rand(0, 1000));
    }catch(\Exception $e){
        \ErrorCatcher::registerError($e, 'e11', ['tag' => '111']);
        //var_dump($e);
        //die();
    }

}