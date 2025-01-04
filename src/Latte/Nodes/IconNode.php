<?php

namespace App\Latte\Nodes;

use App\Models\DataObjects\FontAwesome\IconType;
use App\Services\FontAwesomeManager;
use Generator;
use InvalidArgumentException;
use Latte\Compiler\Node;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\Php\ModifierNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Lsr\Core\App;

class IconNode extends StatementNode
{
    public ModifierNode $modifier;
    public ArrayNode $args;

    public ?TextNode $static = null;
    public IconType | ExpressionNode $style;
    public ExpressionNode $icon;
    public ExpressionNode $classes;
    public bool $addDynamic = true;

    public static function create(Tag $tag) : Node {
        $tag->expectArguments();

        $node = $tag->node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $args = $node->args->toArguments();
        $node->style = $args['style']?->value ?? $args[0]?->value ?? 'solid';

        try {
            $style = NodeHelpers::toValue($node->style, constants: true);
            if (is_string($style)) {
                $node->style = IconType::from($style);
            }
            else {
                if ($style instanceof IconType) {
                    $node->style = $style;
                }
            }
        } catch (InvalidArgumentException) {

        }

        return self::createNode($node);
    }

    private static function createNode(IconNode $node) : Node {
        $args = $node->args->toArguments();
        $node->icon = $args['name']?->value ?? $args[0]?->value ?? '';
        $node->classes = $args['classes']?->value ?? $args[1]?->value ?? new ArrayNode();

        $icon = null;
        try {
            $icon = NodeHelpers::toValue($node->icon, constants: true);
        } catch (InvalidArgumentException) {

        }

        $node->addDynamic = !($node->style instanceof IconType) || !isset($icon) || !is_string($icon);

        if (!$node->addDynamic) {
            /** @var FontAwesomeManager $manager */
            $manager = App::getService('fontawesome');
            $manager->addIcon($node->style, $icon);
        }
        return $node;
    }

    public static function createSolid(Tag $tag) : Node {
        $tag->expectArguments();

        $node = $tag->node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $node->style = IconType::SOLID;
        return self::createNode($node);
    }

    public static function createRegular(Tag $tag) : Node {
        $tag->expectArguments();

        $node = $tag->node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $node->style = IconType::REGULAR;
        return self::createNode($node);
    }

    public static function createBrand(Tag $tag) : Node {
        $tag->expectArguments();

        $node = $tag->node = new self();
        $node->args = $tag->parser->parseArguments();
        $node->modifier = $tag->parser->parseModifier();
        $node->modifier->escape = false;

        $node->style = IconType::BRAND;
        return self::createNode($node);
    }

    public function print(PrintContext $context) : string {
        $icon = $context->format(
          <<<'XX'
            $ʟ_style = %node;
            $ʟ_icon = %node;
            $ʟ_tmp = %node;
            if (is_string($ʟ_tmp)) {
              $ʟ_tmp = [$ʟ_tmp];
            }
            $ʟ_tmp = array_filter($ʟ_tmp);
            echo '<i class="fa-'.($ʟ_style instanceof \App\Models\DataObjects\FontAwesome\IconType ? $ʟ_style->value : $ʟ_style).' fa-'.$ʟ_icon.($ʟ_tmp ? ' '.LR\Filters::escapeHtmlAttr(implode(" ", array_unique($ʟ_tmp))) : '').'"></i>' %line;
            XX,
          $this->style,
          $this->icon,
          $this->classes,
          $this->position,
        );
        if ($this->addDynamic) {
            return $icon."\App\Core\App::getService('fontawesome')->addIcon($ʟ_style,$ʟ_icon);\n";
        }

        return $icon;
    }

    public function &getIterator() : Generator {
        yield $this->icon;
        yield $this->classes;
    }
}
