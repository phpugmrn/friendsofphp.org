<?php declare(strict_types=1);

namespace Fop\MeetupCom;

use DateTimeInterface;
use DateTimeZone;
use Fop\Entity\Location;
use Fop\Entity\Meetup;
use Fop\Entity\TimeSpan;
use Fop\MeetupCom\Api\MeetupComApi;
use Nette\Utils\DateTime;

final class MeetupImporter
{
    /**
     * @var MeetupComApi
     */
    private $meetupComApi;

    /**
     * @var string[]
     */
    private $groupsHavingMeetup = [];

    /**
     * @var DateTimeInterface
     */
    private $maxForecastDateTime;

    public function __construct(int $maxForecastDays, MeetupComApi $meetupComApi)
    {
        $this->maxForecastDateTime = DateTime::from('+' . $maxForecastDays . 'days');
        $this->meetupComApi = $meetupComApi;
    }

    /**
     * @param int[] $groupIds
     * @return Meetup[]
     */
    public function importForGroupIds(array $groupIds): array
    {
        $meetups = [];
        $this->groupsHavingMeetup = [];

        foreach ($this->meetupComApi->getMeetupsByGroupsIds($groupIds) as $meetup) {
            $timeSpan = $this->createTimeSpanFromEventData($meetup);

            if ($this->shouldSkipMeetup($timeSpan, $meetup)) {
                continue;
            }

            $meetups[] = $this->createMeetupFromEventData($meetup, $timeSpan);
        }

        return $meetups;
    }

    /**
     * @param mixed[] $meetup
     */
    private function shouldSkipMeetup(TimeSpan $timeSpan, array $meetup): bool
    {
        // skip past meetups
        if ($meetup['status'] !== 'upcoming') {
            return true;
        }

        // skip meetups too far in the future
        if ($timeSpan->getStartDateTime() > $this->maxForecastDateTime) {
            return true;
        }

        // draft event, not ready yet
        if (! isset($meetup['venue'])) {
            return true;
        }

        $groupName = $meetup['group']['name'];

        // keep only 1 nearest meetup for the group - keep it present and less crowded
        if (in_array($groupName, $this->groupsHavingMeetup, true)) {
            return true;
        }

        $this->groupsHavingMeetup[] = $groupName;

        return false;
    }

    /**
     * @param mixed[] $event
     */
    private function createMeetupFromEventData(array $event, TimeSpan $timeSpan): Meetup
    {
        $venue = $event['venue'];

        $location = new Location($venue['city'], $venue['localized_country_name'], $venue['lon'], $venue['lat']);

        return new Meetup($event['name'], $event['group']['name'], $timeSpan, $location, $event['event_url']);
    }

    /**
     * @param mixed[] $meetup
     */
    private function createTimeSpanFromEventData(array $meetup): TimeSpan
    {
        // not sure why it adds extra "000" in the end
        $time = $this->normalizeTimestamp($meetup['time']);
        $utcOffset = $this->normalizeTimestamp($meetup['utc_offset']);

        $startDateTime = $this->createUtcDateTime($time, $utcOffset);

        if (isset($meetup['duration']) && $meetup['duration']) {
            $duration = $this->normalizeTimestamp($meetup['duration']);
            $endDateTime = $startDateTime->modifyClone('+' . $duration . ' seconds');
        } else {
            $endDateTime = null;
        }

        return new TimeSpan($startDateTime, $endDateTime);
    }

    private function createUtcDateTime(int $time, int $utcOffset): DateTime
    {
        return DateTime::from($time + $utcOffset)
            ->setTimezone(new DateTimeZone('UTC'));
    }

    private function normalizeTimestamp(int $timestamp): int
    {
        return (int) substr((string) $timestamp, 0, -3);
    }
}