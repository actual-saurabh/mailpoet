<?php
namespace MailPoet\Models;

if(!defined('ABSPATH')) exit;

class NewsletterTemplate extends Model {
  public static $_table = MP_NEWSLETTER_TEMPLATES_TABLE;

  function __construct() {
    parent::__construct();

    $this->addValidations('name', array(
      'required' => __('You need to specify a name.')
    ));
    $this->addValidations('body', array(
      'required' => __('Template body cannot be empty.')
    ));
  }

  function asArray() {
    $template = parent::asArray();
    if(isset($template['body'])) {
      $template['body'] = json_decode($template['body'], true);
    }
    return $template;
  }

  static function createOrUpdate($data = array()) {
    $template = false;

    if(isset($data['id']) && (int) $data['id'] > 0) {
      $template = self::findOne((int) $data['id']);
    }

    if($template === false) {
      $template = self::create();
      $template->hydrate($data);
    } else {
      unset($data['id']);
      $template->set($data);
    }

    $template->save();
    return $template;
  }
}
