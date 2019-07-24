<?php
//权限管理
Route::get('dbdic', ['as' => 'dbdic', 'uses' => 'Zhengwhizz\DBDic\Controllers\DBDicController@index']);
Route::get('dbdic/export/{type}', ['as' => 'dbdic.export', 'uses' => 'Zhengwhizz\DBDic\Controllers\DBDicController@export']);
