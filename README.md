cakephp-gearman
===============

Gearman utilities for CakePHP

Cakephp-Gearman is an plugin to integrate gearman into cakephp applications.

Installation
============
1. install the gearman php extension ( @see http://gearman.org/getting-started/ for instructions )
2. install the joze_zap's cakephp gearman plugin (clone it and load it like u usually do with other cake plugins)
3. implement your own Shell with "public $tasks = ['Gearman.GearmanWorker'];"

Example Usage
=============
[c]
App::uses('GearmanQueue', 'Gearman.Client');
class SomeController extends AppController{

  public function Somefunction(){
    //do awesome stuff
    GearmanQueue::execute('build_newsletter', ['user' => $user]);
  }
  
}
[/c]
[c]
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
         ...
    }
 
}
[c/]

Known issues
============
If you get some mysql connection lost error, you have to change your php.ini like this....
