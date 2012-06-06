<?php

namespace Doctrine\Tests\ODM\PHPCR\Functional\Translation;

use Doctrine\ODM\PHPCR\Mapping\ClassMetadataFactory,
    Doctrine\ODM\PHPCR\Mapping\ClassMetadata,
    Doctrine\ODM\PHPCR\Translation\TranslationStrategy\ChildTranslationStrategy;

use Doctrine\Tests\Models\Translation\Article;
use Doctrine\ODM\PHPCR\Translation\Translation;

class ChildTranslationStrategyTest extends \Doctrine\Tests\ODM\PHPCR\PHPCRFunctionalTestCase
{
    protected $testNodeName = '__test-node__';

    /**
     * @var \Doctrine\ODM\PHPCR\DocumentManager
     */
    private $dm;

    /**
     * @var \PHPCR\SessionInterface
     */
    private $session;

    /**
     * @var \PHPCR\WorkspaceInterface
     */
    private $workspace;

    /**
     * @var ClassMetadata
     */
    private $metadata;

    public function setup()
    {
        $this->dm = $this->createDocumentManager();
        $this->session = $this->dm->getPhpcrSession();
        $this->workspace = $this->dm->getPhpcrSession()->getWorkspace();
        $this->metadata = $this->dm->getClassMetadata('Doctrine\Tests\Models\Translation\Article');
    }

    public function tearDown()
    {
        $this->removeTestNode();
    }

    public function testSaveTranslation()
    {
        // First save some translations
        $data = array();
        $data['topic'] = 'Some interesting subject';
        $data['text'] = 'Lorem ipsum...';

        $node = $this->getTestNode();

        $strategy = new ChildTranslationStrategy();
        $strategy->saveTranslation($data, $node, $this->metadata, 'en');

        // Save translation in another language

        $data['topic'] = 'Un sujet intéressant';

        $strategy->saveTranslation($data, $node, $this->metadata, 'fr');
        $this->dm->flush();

        // Then test we have what we expect in the content repository
        $node_en = $this->session->getNode($this->nodeNameForLocale('en'));
        $node_fr = $this->session->getNode($this->nodeNameForLocale('fr'));

        $this->assertTrue($node_en->hasProperty('topic'));
        $this->assertTrue($node_fr->hasProperty('topic'));
        $this->assertTrue($node_en->hasProperty('text'));
        $this->assertTrue($node_fr->hasProperty('text'));
        $this->assertFalse($node_fr->hasProperty('author'));
        $this->assertFalse($node_en->hasProperty('author'));

        $this->assertEquals('Some interesting subject', $node_en->getPropertyValue('topic'));
        $this->assertEquals('Un sujet intéressant', $node_fr->getPropertyValue('topic'));
        $this->assertEquals('Lorem ipsum...', $node_en->getPropertyValue('text'));
        $this->assertEquals('Lorem ipsum...', $node_fr->getPropertyValue('text'));
    }

    public function testLoadTranslation()
    {
        // First save some translations
        $data = array();
        $data['author'] = 'John Doe';
        $data['topic'] = 'English topic';
        $data['text'] = 'English text';

        $node = $this->getTestNode();

        $strategy = new ChildTranslationStrategy();
        $strategy->saveTranslation($data, $node, $this->metadata, 'en');

        // Save translation in another language

        $data['topic'] = 'Sujet français';
        $data['text'] = null;

        $strategy->saveTranslation($data, $node, $this->metadata, 'fr');
        $this->dm->flush();

        $doc = new Article;
        $doc->author = $data['author'];
        $doc->topic = $data['topic'];
        $doc->setText($data['text']);
        $strategy->loadTranslation($doc, $node, $this->metadata, 'en');

        // And check the translatable properties have the correct value
        $this->assertEquals('English topic', $doc->topic);
        $this->assertEquals('English text', $doc->getText());

        // Load another language and test the document has been updated
        $strategy->loadTranslation($doc, $node, $this->metadata, 'fr');

        $this->assertEquals('Sujet français', $doc->topic);
        $this->assertNull($doc->getText());
    }

    public function testTranslationNullProperties()
    {
        // Create the node in the content repository
        $node = $this->fillTranslations();

        // Then try to read it's translation
        $doc = new Article();

        $strategy = new ChildTranslationStrategy();
        $strategy->loadTranslation($doc, $node, $this->metadata, 'en');

        // And check the translatable properties have the correct value
        $this->assertEquals('English topic', $doc->topic);
        $this->assertEquals('English text', $doc->getText());

        // Load another language and test the document has been updated
        $strategy->loadTranslation($doc, $node, $this->metadata, 'fr');

        $this->assertEquals('Sujet français', $doc->topic);
        $this->assertEquals('Texte français', $doc->getText());
    }

    public function testRemoveTranslation()
    {
        $node = $this->fillTranslations();
        $doc = new Article();

        $subNode_en = $node->getNode(Translation::LOCALE_NAMESPACE . ":en");
        $subNode_fr = $node->getNode(Translation::LOCALE_NAMESPACE . ":fr");

        $strategy = new ChildTranslationStrategy();

        $strategy->removeTranslation($doc, $node, $this->metadata, 'en');

        $this->assertTrue($subNode_en->isDeleted());
        $this->assertTrue($subNode_fr->hasProperty('topic'));
        $this->assertTrue($subNode_fr->hasProperty('text'));
    }

    public function testRemoveAllTranslations()
    {
        $node = $this->fillTranslations();
        $doc = new Article();

        $subNode_en = $node->getNode(Translation::LOCALE_NAMESPACE . ":en");
        $subNode_fr = $node->getNode(Translation::LOCALE_NAMESPACE . ":fr");
        $subNode_de = $node->getNode(Translation::LOCALE_NAMESPACE . ":de");

        $strategy = new ChildTranslationStrategy();

        $strategy->removeAllTranslations($doc, $node, $this->metadata);

        $this->assertTrue($subNode_en->isDeleted());
        $this->assertTrue($subNode_fr->isDeleted());
        $this->assertTrue($subNode_de->isDeleted());
    }

    public function testGetLocaleFor()
    {
        $node = $this->fillTranslations();

        $doc = new Article();

        $strategy = new ChildTranslationStrategy();
        $locales = $strategy->getLocalesFor($doc, $node, $this->metadata);

        $this->assertInternalType('array', $locales);
        $this->assertCount(3, $locales);
        $this->assertContains('fr', $locales);
        $this->assertContains('en', $locales);
        $this->assertContains('de', $locales);
    }

    protected function fillTranslations()
    {
        $node = $this->getTestNode();
        $node->setProperty('author', 'John Doe');

        $subNode = $this->getTranslationNode($node, 'en');
        $subNode->setProperty('topic', 'English topic');
        $subNode->setProperty('text', 'English text');

        $subNode = $this->getTranslationNode($node, 'fr');
        $subNode->setProperty('topic', 'Sujet français');
        $subNode->setProperty('text', 'Texte français');

        $subNode = $this->getTranslationNode($node, 'de');
        $subNode->setProperty('topic', 'Deutscher Betreff');
        $subNode->setProperty('text', 'Deutscher Text');
        $this->session->save();

        return $node;
    }

    protected function getTestNode()
    {
        $this->removeTestNode();
        $node = $this->session->getRootNode()->addNode($this->testNodeName);
        $this->session->save();

        $this->dm->clear();
        return $node;
    }

    protected function getTranslationNode($parentNode, $locale)
    {
        $subNode = $parentNode->addNode(Translation::LOCALE_NAMESPACE . ":$locale");
        $this->session->save();

        $this->dm->clear();
        return $subNode;
    }

    protected function removeTestNode()
    {
        $root = $this->session->getRootNode();
        if ($root->hasNode($this->testNodeName)) {
            $root->getNode($this->testNodeName)->remove();
            $this->session->save();
        }
    }

    static function propertyNameForLocale($locale, $property)
    {
        return Translation::LOCALE_NAMESPACE . '-' . $locale . '-' . $property;
    }

    protected function nodeNameForLocale($locale)
    {
        return '/' . $this->testNodeName . '/' . Translation::LOCALE_NAMESPACE . ":$locale";
    }

}
