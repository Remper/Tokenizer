<?php
/**
 * Some new PHP file
 *
 * @author Yaroslav Nechaev <remper@me.com>
 */

namespace Tokenizer\Features\VectorModel\TF;

use Tokenizer\Features\VectorModel\Cache;
use Tokenizer\Text;
use Tokenizer\Token;

class Log implements AbstractTF {
    /**
     * @var Cache
     */
    private $cache;

    public function __construct()
    {
        $this->cache = new Cache(Cache::FULL);
    }

    public function calculate(Token $token, Text $text)
    {
        if ($this->cache->isMatch($token, $text)) {
            return $this->cache->getValue($token, $text);
        } else {
            $counter = 0;
            foreach ($text->getTokens() as $tok) {
                if ($tok->equals($token))
                    $counter++;
            }

            $value = 0;
            if ($counter != 0) {
                $value = 1 + log($counter);
            }
            $this->cache->addValue($token, $text, $value);
            return $value;
        }
    }

    public function clearCache()
    {
        $this->cache = null;
        $this->cache = new Cache(Cache::FULL);
    }
}