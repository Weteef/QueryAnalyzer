QueryAnalyzer
=============

Module that shows every executed query and the execution time.


##Installation
- Add "weteef/queryanalyzer": "1.*" to the require section of your composer.json
- Attach the QueryAnalyzerProfiler to your DB-Adapter.
```
$serviceManager->get('Zend\Db\Adapter\Adapter')
->setProfiler(new \QueryAnalyzer\Db\Adapter\Profiler\QueryAnalyzerProfiler());
```

##Configuration

Copy the file ```queryanalyzer.global.php.dist``` to the autoload folder an rename it to ```queryanalyzer.global.php```

Add your db adapter to the ```'dbadapter'```array. You can remove the standard entry ```'Zend\Db\Adapter\Adapter'```.

```
'dbadapter' => array(
    'your-db-adapter-name'
),
```
After these steps the analyzer should appear on the bottom right corner of your browser window.

If you want to log Queries you need to define a logger in your service_manager config. Example:
```
'service_manager' => array(
   'factories' => array(
      'myLogger' => function ($sm){
         $logger = new Zend\Log\Logger;
         $writer = new Zend\Log\Writer\Stream(__DIR__.'/../../log');
         $logger->addWriter($writer);

         return $logger;
     }
   )
)
```

And add it to the log array in ```queryanalyzer.config.php```

```
'loggers' => array(
      'myLogger'   
),
```

Queries will be logged with the status: info.