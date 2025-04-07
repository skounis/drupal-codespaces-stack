<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\ItemList;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\DataType\DataTransferObject;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\RoleInterface;

/**
 * Tests for ECA-extended Token replacement behavior.
 *
 * @group eca
 * @group eca_core
 */
class TokenTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'eca',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(static::$modules);
  }

  /**
   * Tests token aliases.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTokenAlias(): void {
    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);

    $body = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);

    /** @var \Drupal\node\NodeInterface $article */
    $article = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'title' => 'Token aliases are awesome!',
      'body' => [
        [
          'value' => $body,
          'summary' => $summary,
          'format' => 'plain_text',
        ],
      ],
    ]);
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'title' => 'But please do not replace me by an alias...',
      'body' => [
        [
          'value' => $body,
          'summary' => $summary,
          'format' => 'plain_text',
        ],
      ],
    ]);
    $node->save();

    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $token_services->addTokenData('article', $article);
    $this->assertEquals('Token aliases are awesome!', $token_services->replace('[article:title]', ['node' => $node]));
    $this->assertEquals('Token aliases are awesome! But please do not replace me by an alias...', $token_services->replace('[article:title] [node:title]', ['node' => $node]));

    $token_services->clearTokenData();
    $token_services->addTokenData('node', $node);
    $token_services->addTokenData('article', $article);
    $this->assertEquals('Token aliases are awesome! But please do not replace me by an alias...', $token_services->replace('[article:title] [node:title]'));

    $token_services->clearTokenData();
    $token_services->addTokenData('article', $article);
    $token_services->addTokenData('node', $node);
    $this->assertEquals('Token aliases are awesome! But please do not replace me by an alias...', $token_services->replace('[article:title] [node:title]'));

    $token_services->clearTokenData();
    $token_services->addTokenData('article', $article);
    $token_services->addTokenData('node', $node);
    $token_services->addTokenData('article', $article);
    $this->assertEquals('But please do not replace me by an alias...', $token_services->replace('[node:title]'));
    $this->assertEquals('Token aliases are awesome!', $token_services->replace('[node:title]', [
      'node' => $article,
    ]), 'Generate a replacement value when using a valid token type.');
    $this->assertEquals('Token aliases are awesome!', $token_services->replace('[article:title]', [
      'node' => $node,
    ]), 'Generate a replacement value using $article and not $node because it is an alias.');
    $this->assertEquals('Token aliases are awesome!', $token_services->replace('[article:title]', [
      'article' => $article,
      'node' => $node,
    ]), 'Generate a replacement value using $article and not $node because it is an alias.');

    $token_services->clearTokenData();
    $token_services->addTokenData('article', $article);
    $this->assertEquals('Token aliases are awesome!', $token_services->replace('[article:title]', [
      'article' => $article,
    ]), 'Using same data as argument and alias must not lead to infinite recursion.');
    $token_services->clearTokenData();
    $token_services->addTokenData('node', $article);
    $this->assertEquals('But please do not replace me by an alias...', $token_services->replace('[node:title]', [
      'node' => $node,
    ]), 'Using same data as argument and alias must not lead to infinite recursion.');
  }

  /**
   * Tests Token replacement of Data Transfer Objects (DTOs).
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testDto(): void {
    $dto = DataTransferObject::create();

    $token = \Drupal::token();
    $token_data = ['dto' => $dto];

    $this->assertEquals('[dto]', $token->replace('[dto]', $token_data), "An empty DTO must behave like it does not exist.");

    // Testing string properties.
    $dto->set('mystring', 'Hello!');
    $dto->set('another', 'Greetings');
    $this->assertEquals('Hello!', $token->replace('[dto:mystring]', $token_data));
    $this->assertEquals('Greetings', $token->replace('[dto:another]', $token_data));

    // Testing the DTO itself as string representation.
    $this->assertEquals(Yaml::encode([
      'mystring' => 'Hello!',
      'another' => 'Greetings',
    ]),
    $token->replace('[dto]', $token_data), "The DTO items must be concatenated when accessed through their parent.");
    $dto->setStringRepresentation('This is a string');
    $this->assertEquals('This is a string', (string) $dto, 'String representation must return the manually defined string.');
    $this->assertEquals('This is a string!', $token->replace('[dto]!', $token_data), "The DTO has now a string representation manually defined, thus Token replacement must include it.");

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);

    $body = $this->randomMachineName(32);
    $summary = $this->randomMachineName(16);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'status' => 1,
      'title' => 'I am the node title.',
      'body' => [
        [
          'value' => $body,
          'summary' => $summary,
          'format' => 'plain_text',
        ],
      ],
    ]);
    $node->save();

    $dto->set('article', $node);
    $this->assertEquals('[dto:article:body]', $token->replace('[dto:article:body]', $token_data), "This must not replace anything, as the user has no access to view the node.");

    // Grant permissions and retry.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content']);
    user_role_grant_permissions(RoleInterface::AUTHENTICATED_ID, ['access content']);
    $this->assertEquals($token->replace('[node:title]', ['node' => $node]), $token->replace('[dto:article:title]', $token_data), "The result must be the same like what the Token replacement generates for the node directly.");
    $this->assertEquals($token->replace('[node:body]', ['node' => $node]), $token->replace('[dto:article:body]', $token_data), "The result must be the same like what the Token replacement generates for the node directly.");
    $this->assertEquals($token->replace('[node:summary]', ['node' => $node]), $token->replace('[dto:article:summary]', $token_data), "The result must be the same like what the Token replacement generates for the node directly.");
    $this->assertEquals($token->replace('[node:nonexistentfield]', ['node' => $node], ['clear' => TRUE]), $token->replace('[dto:article:nonexistentfield]', $token_data, ['clear' => TRUE]), "The result must be the same like what the Token replacement generates for the node directly.");

    // Create another DTO, directly with a string.
    $dto = DataTransferObject::create('Another one!');
    $token_data = ['dto' => $dto];
    $this->assertInstanceOf(DataTransferObject::class, $dto, 'DTO must be a DTO.');
    $this->assertEquals('Another one!', (string) $dto, 'String representation of the DTO must match with the defined string.');
    $this->assertEquals((string) $dto, $token->replace('[dto]', $token_data), 'String representation of the DTO must be also usable as root token.');

    // Create another DTO, directly using the node.
    $dto = DataTransferObject::create($node);
    $token_data = ['dto' => $dto];

    $this->assertInstanceOf(DataTransferObject::class, $dto, 'DTO must be a DTO.');
    $this->assertTrue(isset($dto->body->value), 'DTO must contain the node body field.');
    $this->assertEquals($body, $dto->body->value, 'DTO must hold the same body value as the node.');
    $this->assertEquals($node->body->value, $dto->body->value, 'DTO must hold the same body value as the node.');
    $this->assertEquals('I am the node title.', $token->replace('[dto:title]', $token_data), 'The DTO must generate the same token value as the node title.');
    $this->assertNotEquals('[dto:body]', $token->replace('[dto:body]', $token_data), 'The DTO body value must be replaceable as token.');
    $this->assertEquals($token->replace('[node:body]', ['node' => $node]), $token->replace('[dto:body]', $token_data), 'The DTO must generate the same token value as the node body.');
  }

  /**
   * Tests list operations on a DTO using the Token service.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function testDtoList(): void {
    $dto = DataTransferObject::create();
    $dto->set('+', 'Hello');
    $dto->set('+', 'nice to meet you');
    $dto->set('+', 'good bye');
    $dto->set('+', 'well, not yet');
    $dto->set('+', 'maybe now?');
    $dto->set('-', NULL);
    $dto->set('+', 'hope you enjoy using ECA.');
    $dto->set('-', 'good bye');
    $dto->set('-', 'well, not yet');

    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');
    $token_services->addTokenData('mydto:list', $dto);
    $this->assertTrue($token_services->hasTokenData('mydto'));
    $this->assertNotSame($dto, $token_services->getTokenData('mydto'));
    $this->assertTrue($token_services->hasTokenData('mydto:list'));
    $this->assertSame($dto, $token_services->getTokenData('mydto:list'));
    $this->assertEquals(Yaml::encode([
      "Hello",
      "nice to meet you",
      "hope you enjoy using ECA.",
    ]), $token_services->replace('[mydto:list]'));
    $this->assertEquals("Hello", $token_services->replace('[mydto:list:0]'));
    $this->assertEquals("hope you enjoy using ECA.", $token_services->replace('[mydto:list:2]'));
  }

  /**
   * Tests usages of data attached to the Token service.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTokenData(): void {
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();
    node_add_body_field($node_type);

    $title1 = $this->randomMachineName(16);
    $body1 = $this->randomMachineName(32);
    $summary1 = $this->randomMachineName(16);

    /** @var \Drupal\node\NodeInterface $node */
    $node1 = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'status' => 1,
      'title' => $title1,
      'body' => [
        [
          'value' => $body1,
          'summary' => $summary1,
          'format' => 'plain_text',
        ],
      ],
    ]);
    $node1->save();

    $title2 = $this->randomMachineName(16);
    $body2 = $this->randomMachineName(32);
    $summary2 = $this->randomMachineName(16);

    /** @var \Drupal\node\NodeInterface $node */
    $node2 = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'status' => 1,
      'title' => $title2,
      'body' => [
        [
          'value' => $body2,
          'summary' => $summary2,
          'format' => 'plain_text',
        ],
      ],
    ]);
    $node2->save();

    $title3 = $this->randomMachineName(16);
    $body3 = $this->randomMachineName(32);
    $summary3 = $this->randomMachineName(16);

    /** @var \Drupal\node\NodeInterface $node */
    $node3 = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'status' => 1,
      'title' => $title3,
      'body' => [
        [
          'value' => $body3,
          'summary' => $summary3,
          'format' => 'plain_text',
        ],
      ],
    ]);
    $node3->save();

    $token_services->addTokenData('node1', $node1);
    $token_services->addTokenData('node2', $node2);
    $token_services->addTokenData('node3', $node3);
    $this->assertFalse($token_services->hasTokenData('node'));
    $this->assertTrue($token_services->hasTokenData('node2'));
    $this->assertSame($node1, $token_services->getTokenData('node1'));
    $this->assertSame($node2, $token_services->getTokenData('node2'));
    $this->assertSame($node3, $token_services->getTokenData('node3'));

    // Basic test for token replacement.
    $this->assertEquals('NID: ' . $node1->id(), $token_services->replace('NID: [node1:nid]'));
    $this->assertEquals('TITLE: ' . $node1->label(), $token_services->replace('TITLE: [node1:title]'));
    // ECA supports root-level tokens (tokens without further keys). When not
    // specified otherwise, the entity ID will be used for replacement.
    $this->assertEquals('NID: ' . $node1->id(), $token_services->replace('NID: [node1]'));

    // Expect a DTO when adding nested data.
    $token_services->addTokenData('myobject:node1', $node1);
    $this->assertTrue($token_services->hasTokenData('myobject'));
    $this->assertEquals(DataTransferObject::class, get_class($token_services->getTokenData('myobject')));
    $this->assertInstanceOf(EntityInterface::class, $token_services->getTokenData('myobject:node1'));
    $this->assertNotSame($token_services->getTokenData('myobject'), $token_services->getTokenData('myobject:node1'));

    $nodes_array = [$node1, $node2, $node3];
    $node_list = ItemList::createInstance(ListDataDefinition::create('entity'));
    $node_list->setValue($nodes_array);
    $token_services->addTokenData('myobject:nodelist', $node_list);
    $this->assertSame($node_list, $token_services->getTokenData('myobject:nodelist'));

    $token_services->addTokenData('mylist', $node_list);
    $this->assertTrue($token_services->hasTokenData('mylist'));
    $mylist = $token_services->getTokenData('mylist');
    $this->assertEquals(DataTransferObject::class, get_class($mylist));
    $nodes_found = [];
    foreach ($node_list as $i => $item) {
      $this->assertTrue(isset($mylist->$i));
      $this->assertSame($item->getValue(), $mylist->get($i)->getValue());
      $nodes_found[] = $item->getValue();
    }
    $this->assertEquals($nodes_array, $nodes_found);
    $this->assertEquals($title1, $nodes_found[0]->label());
    $this->assertEquals($title2, $nodes_found[1]->label());
    $this->assertEquals($title3, $nodes_found[2]->label());
    $this->assertEquals($body1, $nodes_found[0]->body->value);
    $this->assertEquals($body2, $nodes_found[1]->body->value);
    $this->assertEquals($body3, $nodes_found[2]->body->value);

    // Test adding a plain string token and use it as a root token.
    $this->assertFalse($token_services->hasTokenData('myroot_token'), 'The token must not yet exist.');
    $token_services->addTokenData('myroot_token', 'A string value');
    $this->assertTrue($token_services->hasTokenData('myroot_token'), 'The token must now exist.');
    $this->assertEquals('A string value', $token_services->replace('[myroot_token]'), 'Token replacement must use root token.');
    $this->assertEquals('Hello: A string value', $token_services->replaceClear('Hello: [myroot_token]'), 'Token replacement must use root token.');
    $this->assertEquals('', $token_services->replaceClear('[myroot_token:nonexistent]'), 'Root token must only return its value when accessed as root token.');

    $this->assertFalse($token_services->hasTokenData('myroot_token:existent'), 'Property token must not yet exist.');
    $token_services->addTokenData('myroot_token:existent', 'I exist.');
    $this->assertTrue($token_services->hasTokenData('myroot_token:existent'), 'Property token must not yet exist.');
    $this->assertEquals('A string value', $token_services->replace('[myroot_token]'), 'Token replacement must use root token.');
    $this->assertEquals('I exist.', $token_services->replace('[myroot_token:existent]'), 'Token replacement must use assigned property when defined.');
    // Remove the assigned property.
    $token_services->addTokenData('myroot_token:existent', '');
    $this->assertFalse($token_services->hasTokenData('myroot_token:existent'), 'Property token must not exist anymore.');
    $this->assertEquals('A string value', $token_services->replace('[myroot_token]'), 'Token replacement must still use root token.');
    // Remove the root token itself.
    $token_services->addTokenData('myroot_token', '');
    $this->assertFalse($token_services->hasTokenData('myroot_token'), 'Root token must not exist anymore.');
    $this->assertEquals('[myroot_token]', $token_services->replace('[myroot_token]'), 'Token replacement not replace root token anymore.');
    $this->assertEquals('', $token_services->replaceClear('[myroot_token]'), 'Token replacement not replace root token anymore.');
  }

  /**
   * Tests DTO built from user input.
   */
  public function testDtoUserInput(): void {
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    $yaml = <<<YAML
key1: val1
key2: val2
YAML;
    $dto = DataTransferObject::fromUserInput($yaml);
    $token_services->addTokenData('mydto', $dto);

    $this->assertEquals(Yaml::encode(['key1' => 'val1', 'key2' => 'val2']), $token_services->replace('[mydto]'));

    $string = $this->randomMachineName();
    $dto = DataTransferObject::fromUserInput($string);
    $token_services->addTokenData('mydto', $dto);

    $this->assertEquals($string, $token_services->replaceClear('[mydto]'));

    $string = 'key1,key2-key3';
    $dto = DataTransferObject::fromUserInput($string);
    $token_services->addTokenData('mydto', $dto);
    $this->assertEquals(Yaml::encode(['key1', 'key2-key3']), $token_services->replace('[mydto]'));

    $string = 'key1, key2-key3';
    $dto = DataTransferObject::fromUserInput($string);
    $token_services->addTokenData('mydto', $dto);
    $this->assertEquals(Yaml::encode(['key1', 'key2-key3']), $token_services->replace('[mydto]'));

    $string = 'key1:val1, key2: val2';
    $dto = DataTransferObject::fromUserInput($string);
    $token_services->addTokenData('mydto', $dto);
    $this->assertEquals(Yaml::encode(['key1' => 'val1', 'key2' => 'val2']), $token_services->replace('[mydto]'));
  }

  /**
   * Tests plain text vs HTML markup replacement.
   */
  public function testPlainText(): void {
    /** @var \Drupal\eca\Token\TokenInterface $token_services */
    $token_services = \Drupal::service('eca.token_services');

    // Create the Article content type with a standard body field.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create(['type' => 'article', 'name' => 'Article']);
    $node_type->save();

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'article',
      'tnid' => 0,
      'uid' => 0,
      'status' => 1,
      'title' => 'Terms & Conditions',
    ]);
    $node->save();

    $token_services->addTokenData('node', $node);
    $this->assertEquals('Prefix Terms &amp; Conditions Suffix', $token_services->replace('Prefix [node:title] Suffix'));
    $this->assertEquals('Prefix Terms & Conditions Suffix', $token_services->replacePlain('Prefix [plain:node:title] Suffix'));
  }

}
