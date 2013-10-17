QueryAnalyzer
=============

Module that shows every executed query and the execution time.


##Installation
- Add "weteef/queryanalyzer": "1.*" to the require section of your composer.json
- Add ```QueryAnalyzer``` to your application.config.php
- Attach the QueryAnalyzerProfiler to your DB-Adapter.
```
$serviceManager->get('Zend\Db\Adapter\Adapter')
->setProfiler(new \QueryAnalyzer\Db\Adapter\Profiler\QueryAnalyzerProfiler());
```

- If your DB Adapater does not have the name Zend\Db\Adapter\Adapter you need to create an alias in the config:
```
'aliases' => array(
    'Zend\Db\Adapter\Adapter' => 'your-db-adapter-name',
)
```

After these steps the analyzer should appear on the bottom right corner of your browser window.


##Configuration

If you want to configure the Queryanalyzer copy the file ```queryanalyzer.global.php.dist``` to the autoload folder an rename it to ```queryanalyzer.global.php```

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