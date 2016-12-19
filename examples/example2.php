<?php
include_once '../src/client.php';
\ErrorCatcher::config('http://demo.mubinov.com', 'TveCd90D59CDmt1S7D0c68a27jbHffXAZU2FzfXA', 'u122', ['email' => '1234']);
\ErrorCatcher::setErrorHandler();

function test2(){
    throw new Exception('omg');

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
