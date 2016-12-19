<?php
include_once '../src/client.php';
\ErrorCatcher::config('http://errors_catcher.dev', 'u4cgJ267cZjt5RSwiTb2Eh4tlJUa767qflUhTSoi', 'u122', ['email' => '1234'], true);
\ErrorCatcher::setErrorHandler();

function test2(){
    throw new Exception('omg', 123);

    //qwewqew();
}

function test1(){
    test2();
}

try{
    test1($index);
}catch(\Exception $e){
    \ErrorCatcher::registerError($e, 'e11', ['tag' => '111']);
    //var_dump($e);
    //die();
}
