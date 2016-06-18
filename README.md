cakephp-gearman
===============

Gearman utilities for CakePHP

Cakephp-Gearman is a plugin to integrate gearman into cakephp applications.

> This repository is no longer maintained. You might want to try
> [cvo-technologies/cakephp-gearman](https://github.com/cvo-technologies/cakephp-gearman)
> instead.

Installation
============
1. install the gearman php extension ( @see http://gearman.org/getting-started/ for instructions )
2. install the joze_zap's cakephp gearman plugin (clone it and load it like u usually do with other cake plugins)
3. implement your own Shell with ```php public $tasks = ['Gearman.GearmanWorker'];```

Example Usage
=============
```php
App::uses('GearmanQueue', 'Gearman.Client');
class SomeController extends AppController{

  public function Somefunction(){
    //do awesome stuff
    GearmanQueue::execute('build_newsletter', ['User' => $user]);
  }
  
}
```
```php
App::uses('AppShell', 'Console/Command');
App::uses('CakeEmail', 'Network/Email');
 
/**
 * This class is responsible for building email templates per user and sending them as newsletter
 *
 */
class NewsletterShell extends AppShell {
 
 
/**
 * List of Tasks to be used
 *
 * @return void
 */
    public $tasks = ['Gearman.GearmanWorker'];
 
/**
 * Starts a worker server and make it ready to serve new build_newsletter jobs
 *
 * @return void
 */
    public function server() {
        $this->GearmanWorker->addFunction('build_newsletter', $this, 'sendNewsLetter');
        $this->GearmanWorker->work();
    }
 
/**
 * Builds and sends a newsletter to a user for an specific location
 *
 * @param array $data containing 'user' and 'location' keys
 * @return void
 */
    public function sendNewsLetter($data) {
        $Email = new CakeEmail('smtp');
        try{
            $Email->template('exampleContent', 'someLayout')
                ->to($data['User']['email'])
                ->subject('First Gearman email.')
                ->emailFormat('text')
                ->viewVars(array('data' => $data))
                ->send();
        }catch(Exception $e){
            //handle error
        }
    }
 
}
```
