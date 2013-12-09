<?php

use Nette\Utils\Strings as String;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;
use Nette\Templating\FileTemplate;


class DocsetCommand extends Command
{

	const TMP_NAME = '.docset.sqlite';

	protected function configure()
	{
		$this
			->setName('docset')
			->setDescription('Create docset from ApiGen html')
			->addArgument(
				'docDir',
				InputArgument::REQUIRED,
				'Path to root of ApiGen generated documentation'
			)
			->addArgument(
				'output',
				InputArgument::REQUIRED,
				'Include the .docset suffix'
			)
			->addOption(
				'icon',
				NULL,
				InputOption::VALUE_REQUIRED,
				'Path to icon file'
			)
			->addOption(
				'name',
				NULL,
				InputOption::VALUE_REQUIRED,
				'Name of Docset'
			)
			->addOption(
				'keyword',
				NULL,
				InputOption::VALUE_REQUIRED,
				'Search keyword (Dash feature)'
			)
			->addOption(
				'index',
				NULL,
				InputOption::VALUE_REQUIRED,
				'Default documentation file (Dash feature)'
			)
		;
	}

	private function writePlist(InputInterface $input, $docDir, $plistPath)
	{
		$parser = new DocParser($docDir);

		$template = new FileTemplate(__DIR__ . '/Info.plist');
		$template->registerFilter(new Nette\Latte\Engine);
		$template->id = $parser->id;
		$template->name = $input->getOption('name') ?: $parser->name;
		$template->keyword = $input->getOption('keyword') ?: 'php';
		$template->index = $input->getOption('index') ?: FALSE;

		ob_start();
		$template->render();
		file_put_contents($plistPath, ob_get_clean());
	}

	private function fixCss($output)
	{
		$css = "$output/Contents/Resources/Documents/resources/style.css";
		$new = __DIR__ . '/style.css';
		file_put_contents($css, file_get_contents($new), FILE_APPEND);
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$docDir = $input->getArgument('docDir');
		
		$build = $this->getApplication()->find('build');
		$args = [
			'command' => 'build',
			'docDir' => $docDir,
			'--output' => self::TMP_NAME,
		];
		$build->run(new ArrayInput($args), $output);

		$path = $input->getArgument('output');
		if (file_exists($path))
		{
			$output->writeln("<error>File $path already exists</error>");
			return FALSE;
		}
		$res = @mkdir("$path/Contents/Resources/Documents/resources", 0777, TRUE);
		if (!$res)
		{
			$output->writeln("<error>Error writing to $path</error>");
			return FALSE;
		}

		rename(self::TMP_NAME, "$path/Contents/Resources/docSet.dsidx"); // TODO check errors

		$this->writePlist($input, $docDir, "$path/Contents/Info.plist");

		if ($icon = $input->getOption('icon'))
		{
			if (!file_exists($icon))
			{
				$output->writeln("<error>Icon file not found at $path, skipping it</error>");
			}
			else
			{
				copy($icon, "$path/icon.png");
			}
		}

		$finder = new Finder();
		foreach ($finder->files()->depth(0)->in($docDir) as $file)
		{
			copy($file->getRealpath(), "$path/Contents/Resources/Documents/" . $file->getBasename());
		}
		foreach ($finder->files()->depth(0)->in("$docDir/resources") as $file)
		{
			copy($file->getRealpath(), "$path/Contents/Resources/Documents/resources/" . $file->getBasename());
		}
		$this->fixCss($path);

		$output->writeln("Done");
	}
}
