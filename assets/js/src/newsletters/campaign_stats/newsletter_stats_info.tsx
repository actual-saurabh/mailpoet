import React from 'react';
import MailPoet from 'mailpoet';
import Heading from 'common/typography/heading/heading';
import Grid from 'common/grid';
import { Button } from 'common';
import { NewsletterType } from './newsletter_type';

type Props = {
  newsletter: NewsletterType
}

export const NewsletterStatsInfo = ({
  newsletter,
}: Props) => {
  const newsletterDate = newsletter.queue.scheduled_at || newsletter.queue.created_at;
  return (
    <Grid.ThreeColumns className="mailpoet-stats-info">
      <div>
        <Heading level={1}>{newsletter.subject}</Heading>
        <div>
          <b>
            {MailPoet.Date.short(newsletterDate)}
            {' • '}
            {MailPoet.Date.time(newsletterDate)}
          </b>
        </div>
        {Array.isArray(newsletter.segments) && newsletter.segments.length && (
          <div className="mailpoet-stats-segments">
            {MailPoet.I18n.t('statsToSegments')}
            {': '}
            {newsletter.segments.map((segment) => (
              <span
                className="mailpoet-stats-segments-segment"
                key={segment.name}
              >
                {segment.name}
              </span>
            ))}
          </div>
        )}
      </div>
      <div />
      <div className="mailpoet-stats-info-sender-preview">
        <div>
          {newsletter.sender_address && (
            <div className="mailpoet-stats-info-key-value">
              <span className="mailpoet-stats-info-key">
                {MailPoet.I18n.t('statsFromAddress')}
                {': '}
              </span>
              {newsletter.sender_address}
            </div>
          )}
          {newsletter.reply_to_address && (
            <div className="mailpoet-stats-info-key-value">
              <span className="mailpoet-stats-info-key">
                {MailPoet.I18n.t('statsReplyToAddress')}
                {': '}
              </span>
              {newsletter.reply_to_address}
            </div>
          )}
          {newsletter.ga_campaign && (
            <div className="mailpoet-stats-info-key-value">
              <span className="mailpoet-stats-info-key">
                {MailPoet.I18n.t('googleAnalytics')}
                {': '}
              </span>
              {newsletter.ga_campaign}
            </div>
          )}
        </div>
        <div>
          <Button
            href={newsletter.preview_url}
            target="_blank"
            rel="noopener noreferrer"
          >
            {MailPoet.I18n.t('statsPreviewNewsletter')}
          </Button>
        </div>
      </div>
    </Grid.ThreeColumns>
  );
};
