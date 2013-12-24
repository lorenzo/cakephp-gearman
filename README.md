cakephp-gearman
===============

Gearman utilities for CakePHP

Cakephp-Gearman is an awesome plugin to integrate gearman into cakephp applications.

Installation
============
1. install the gearman php extension ( @see http://gearman.org/getting-started/ for instructions )
2. implement your own Shell with "public $tasks = ['Gearman.GearmanWorker'];" property and a server() function 
which allows you to register functions via GearmanWorker->addFunction and starts the server via GearmanWorker->work()
3. 
