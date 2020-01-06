<?php

namespace MailPoet\Models;

/**
 * @property int $newsletterId
 * @property int $postId
 * @property string $updatedAt
 */
class NewsletterPost extends Model {
  public static $_table = MP_NEWSLETTER_POSTS_TABLE;

  public static function getNewestNewsletterPost($newsletterId) {
    return self::where('newsletter_id', $newsletterId)
      ->orderByDesc('created_at')
      ->findOne();
  }
}
