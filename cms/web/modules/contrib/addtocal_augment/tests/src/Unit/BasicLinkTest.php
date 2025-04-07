<?php

declare(strict_types=1);

namespace Drupal\Tests\addtocal_augment\Unit;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the "addtocal_augment" implementation.
 *
 * @coversDefaultClass \Drupal\addtocal_augment\Plugin\DateAugmenter\AddToCal
 * @group addtocal_augment
 *
 * @see \Drupal\addtocal_augment\Plugin\DateAugmenter\AddToCal
 */
class BasicLinkTest extends UnitTestCase {

  /**
   * A mocked version of the AddToCal plugin.
   *
   * @var \Drupal\addtocal_augment\Plugin\DateAugmenter\AddToCal
   */
  protected $addtocal;

  /**
   * Before a test method is run, setUp() is invoked.
   */
  public function setUp(): void {
    parent::setUp();

    $config_factory = $this->getConfigFactoryStub(
      [
        'system.date' => [
          'timezone' => ['America/Chicago'],
        ],
        'system.site' => [
          'name' => 'My awesome test site',
        ],
      ]
    );
    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    \Drupal::setContainer($container);
    $this->addtocal = new TestAddToCal([], 'smart_date', [], $config_factory);
  }

  /**
   * Test AddToCal::generateLinks with a data provider method.
   *
   * Uses the data provider method to test with a wide range of words/stems.
   */
  public function testCal() {
    foreach ($this->getData() as $data) {
      $actual = $this->addtocal->buildLinks([], $data['input']['start'], $data['input']['end'], $data['input']);
      $ical_render = $this->addtocal->implodeRecursive($this->addtocal->separator, $actual['ical']);
      $outlook_render = $this->addtocal->implodeRecursive($this->addtocal->separator, $actual['outlook']);
      $this->assertEquals($data['expected']['google'], $actual['google'], 'Google output matches');
      $this->assertEquals($data['expected']['outlook'], explode($this->addtocal->separator, $outlook_render), 'Outlook output matches');
      $this->assertEquals($data['expected']['ical'], explode($this->addtocal->separator, $ical_render), 'iCal output matches');
    }
  }

  /**
   * Data provider for testCal().
   *
   * @return array
   *   Nested arrays of values to check:
   *   - $word
   *   - $stem
   */
  public function getData() {
    $cdt = new \DateTimeZone('America/Chicago');
    $jpn = new \DateTimeZone('Asia/Tokyo');
    $settings = ['langcode' => 'en'];
    $data = [];
    $data['A single event spanning one hour'] = [
      'expected' => [
        'ical' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'BEGIN:VTIMEZONE',
          'TZID:America/Chicago',
          'BEGIN:STANDARD',
          'TZOFFSETFROM:-0500',
          'TZOFFSETTO:-0500',
          'END:STANDARD',
          'END:VTIMEZONE',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:A%20single%20event%20spanning%20one%20hour',
          'DTSTAMP:20211027T050000Z',
          'DTSTART;TZID=America/Chicago:20211029T150000',
          'DTEND;TZID=America/Chicago:20211029T160000',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'outlook' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:A%20single%20event%20spanning%20one%20hour',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029T200000Z',
          'DTEND:20211029T210000Z',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'google' => [
          'ctz' => 'America/Chicago',
          'text' => 'A single event spanning one hour',
          'dates' => '20211029T150000/20211029T160000',
        ],
      ],
      'input' => [
        'entity' => '',
        'start' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 15:00:00', $cdt, $settings),
        'end' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 16:00:00', $cdt, $settings),
        'settings' => [
          'title' => 'A single event spanning one hour',
          'retain_spacing' => FALSE,
          'ellipsis' => TRUE,
        ],
      ],
    ];
    $data['A recurring event, in Tokyo'] = [
      'expected' => [
        'ical' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'BEGIN:VTIMEZONE',
          'TZID:Asia/Tokyo',
          'BEGIN:STANDARD',
          'TZOFFSETFROM:+0900',
          'TZOFFSETTO:+0900',
          'END:STANDARD',
          'END:VTIMEZONE',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:Test%20title%20here',
          'DTSTAMP:20211027T050000Z',
          'DTSTART;TZID=Asia/Tokyo:20211029T150000',
          'DTEND;TZID=Asia/Tokyo:20211029T160000',
          'FREQ=DAILY;BYDAY=MO;COUNT=2',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'outlook' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:Test%20title%20here',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029T060000Z',
          'DTEND:20211029T070000Z',
          'FREQ=DAILY;BYDAY=MO;COUNT=2',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'google' => [
          'ctz' => 'Asia/Tokyo',
          'text' => 'Test title here',
          'dates' => '20211029T150000/20211029T160000',
          'recur' => 'FREQ=DAILY;BYDAY=MO;COUNT=2',
        ],
      ],
      'input' => [
        'entity' => '',
        'repeats' => 'FREQ=DAILY;BYDAY=MO;COUNT=2',
        'start' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 15:00:00', $jpn, $settings),
        'end' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 16:00:00', $jpn, $settings),
        'settings' => [
          'title' => 'Test title here',
          'retain_spacing' => FALSE,
          'ellipsis' => TRUE,
        ],
      ],
    ];
    $data['An all-day event'] = [
      'expected' => [
        'ical' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'BEGIN:VTIMEZONE',
          'TZID:America/Chicago',
          'BEGIN:STANDARD',
          'TZOFFSETFROM:-0500',
          'TZOFFSETTO:-0500',
          'END:STANDARD',
          'END:VTIMEZONE',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:An%20all-day%20event%20%231',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029',
          'DTEND:20211030',
          'DESCRIPTION:Here%20is%20a%20%232%20description...',
          'LOCATION:Zoom%20%233',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'outlook' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:An%20all-day%20event%20%231',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029T050000Z',
          'DTEND:20211030T050000Z',
          'DESCRIPTION:Here%20is%20a%20%232%20description...',
          'LOCATION:Zoom%20%233',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'google' => [
          'ctz' => 'America/Chicago',
          'text' => 'An all-day event #1',
          'dates' => '20211029/20211030',
          'details' => 'Here is a #2 description...',
          'location' => 'Zoom #3',
        ],
      ],
      'input' => [
        'entity' => '',
        'allday' => TRUE,
        'start' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 00:00:00', $cdt, $settings),
        'end' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-30 00:00:00', $cdt, $settings),
        'settings' => [
          'title' => 'An all-day event #1',
          'description' => 'Here is a #2 description',
          'location' => 'Zoom #3',
          'retain_spacing' => FALSE,
          'ellipsis' => TRUE,
        ],
      ],
    ];
    $data['Event with linebreaks'] = [
      'expected' => [
        'ical' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'BEGIN:VTIMEZONE',
          'TZID:America/Chicago',
          'BEGIN:STANDARD',
          'TZOFFSETFROM:-0500',
          'TZOFFSETTO:-0500',
          'END:STANDARD',
          'END:VTIMEZONE',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:Linebreaks',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029',
          'DTEND:20211030',
          'DESCRIPTION:Description%20first%20line%5CnDescription%20second%20line...',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'outlook' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:Linebreaks',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029T050000Z',
          'DTEND:20211030T050000Z',
          'DESCRIPTION:Description%20first%20line%5CnDescription%20second%20line...',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'google' => [
          'ctz' => 'America/Chicago',
          'text' => 'Linebreaks',
          'dates' => '20211029/20211030',
          'details' => 'Description first line
Description second line...',
        ],
      ],
      'input' => [
        'entity' => '',
        'allday' => TRUE,
        'start' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 00:00:00', $cdt, $settings),
        'end' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-30 00:00:00', $cdt, $settings),
        'settings' => [
          'title' => 'Linebreaks',
          'description' => 'Description first line
Description second line',
          'retain_spacing' => TRUE,
          'ellipsis' => TRUE,
        ],
      ],
    ];
    $data['No ellipsis'] = [
      'expected' => [
        'ical' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'BEGIN:VTIMEZONE',
          'TZID:America/Chicago',
          'BEGIN:STANDARD',
          'TZOFFSETFROM:-0500',
          'TZOFFSETTO:-0500',
          'END:STANDARD',
          'END:VTIMEZONE',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:No%20ellipsis',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029',
          'DTEND:20211030',
          'DESCRIPTION:My%20nice%20description%20that%20should%20be%20truncated%20but%20not%20have%20el',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'outlook' => [
          'data:text/calendar;charset=utf8,BEGIN:VCALENDAR',
          'PRODID:My awesome test site',
          'VERSION:2.0',
          'BEGIN:VEVENT',
          'UID:uuid12345',
          'SUMMARY:No%20ellipsis',
          'DTSTAMP:20211027T050000Z',
          'DTSTART:20211029T050000Z',
          'DTEND:20211030T050000Z',
          'DESCRIPTION:My%20nice%20description%20that%20should%20be%20truncated%20but%20not%20have%20el',
          'END:VEVENT',
          'END:VCALENDAR',
        ],
        'google' => [
          'ctz' => 'America/Chicago',
          'text' => 'No ellipsis',
          'dates' => '20211029/20211030',
          'details' => 'My nice description that should be truncated but not have el',
        ],
      ],
      'input' => [
        'entity' => '',
        'allday' => TRUE,
        'start' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-29 00:00:00', $cdt, $settings),
        'end' => DrupalDateTime::createFromFormat('Y-m-d H:i:s', '2021-10-30 00:00:00', $cdt, $settings),
        'settings' => [
          'title' => 'No ellipsis',
          'description' => 'My nice description that should be truncated but not have ellipsis',
          'retain_spacing' => FALSE,
          'ellipsis' => FALSE,
        ],
      ],
    ];

    foreach ($data as $key => $value) {
      // Send a Node mock, because NodeInterface cannot be mocked.
      $mock_node = $this->createMock('Drupal\node\Entity\Node');
      $mock_node->expects($this->any())
        ->method('uuid')
        ->willReturn('uuid12345');
      // Mock the entity->label() method from the test data.
      $mock_node->expects($this->any())
        ->method('label')
        ->willReturn($value['input']['settings']['title']);
      $data[$key]['input']['entity'] = $mock_node;
    }
    return $data;
  }

}
