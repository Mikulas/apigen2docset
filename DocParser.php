<?php

use Nette\Utils\Strings as String;
use Symfony\Component\DomCrawler\Crawler;


class DocParser extends Nette\Object
{

	/** @var string path */
	private $docDir;


	public function __construct($docDir)
	{
		if (!file_exists("$docDir/index.html"))
		{
			throw new \Exception("$docDir does not contain ApiGen documentation");
		}
		$this->docDir = $docDir;
	}

	public function getCrawler($file)
	{
		return new Crawler(file_get_contents("$this->docDir/$file"));
	}

	public function getGeneratorVersion()
	{
		$meta = $this->getCrawler('index.html')->filterXPath('//meta[@name="generator"]')->first();
		return substr($meta->attr('content'), strlen('ApiGen '));
	}

	public function getName()
	{
		$node = $this->getCrawler('index.html')->filterXPath('//*[@id="content"]/h1')->first();
		return $node->text();
	}

	public function getId()
	{
		return String::webalize($this->getName());
	}

}
