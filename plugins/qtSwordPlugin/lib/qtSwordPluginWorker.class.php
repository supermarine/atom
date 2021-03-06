<?php

/*
 * This file is part of the Access to Memory (AtoM) software.
 *
 * Access to Memory (AtoM) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Access to Memory (AtoM) is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Access to Memory (AtoM).  If not, see <http://www.gnu.org/licenses/>.
 */

class qtSwordPluginWorker extends arBaseJob
{
  /**
   * @see arBaseJob::$requiredParameters
   */
  protected $extraRequiredParameters = array('information_object_id');

  protected $dispatcher = null;

  protected function log($message)
  {
    $this->dispatcher->notify(new sfEvent($this, 'gearman.worker.log',
      array('message' => $message)));
  }

  public function runJob($package)
  {
    $this->dispatcher = sfContext::getInstance()->getEventDispatcher();

    $this->log('A new job has started to being processed.');

    if (!is_writable(sfConfig::get('sf_web_dir').DIRECTORY_SEPARATOR.sfConfig::get('app_upload_dir')))
    {
      $this->log('Job failed: Read-write access needed in {sf_web_dir}/{app_upload_dir}.');
      $this->error('Read-write access needed in {sf_web_dir}/{app_upload_dir}.');

      return false;
    }

    if (isset($package['location']))
    {
      $this->log(sprintf('A package was deposited by reference.'));
      $this->log(sprintf('Location: %s', $package['location']));
    }
    else if (isset($package['filename']))
    {
      $this->log(sprintf('A package was deposited by upload.'));
    }

    $this->log(sprintf('Processing...'));

    $resource = QubitInformationObject::getById($package['information_object_id']);

    $this->log(sprintf('Object slug: %s', $resource->slug));

    $extractor = qtPackageExtractorFactory::build($package['format'],
      $package + array('resource' => $resource, 'job' => $job));

    $extractor->run();

    // Save ES documents in the batch queue
    // We need to call the magic method explictly
    // because the object isn't destroyed in a worker
    QubitSearch::getInstance()->__destruct();

    $this->job->setStatusCompleted();
    $this->job->save();

    $this->log(sprintf('Job finished.'));

    return true;
  }
}
