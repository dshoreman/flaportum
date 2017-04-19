<?php namespace Flaportum\Markdown;

use League\HTMLToMarkdown\Environment;
use League\HTMLToMarkdown\HtmlConverter;

class Markdown
{
    /**
     * @var HtmlConverter
     */
    protected $markdown;

    public function __construct()
    {
        $env = Environment::createDefaultEnvironment([
            'hard_break' => true,
            'strip_tags' => true,
        ]);

        $env->addConverter(new LinkConverter());

        $this->markdown = new HtmlConverter($env);
    }

    public function convert($str)
    {
        if (!$this->markdown) {
            $this->init();
        }

        return $this->markdown->convert($str);
    }
}
