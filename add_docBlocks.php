<?php

require 'vendor/autoload.php';

use PhpParser\{Node, NodeTraverser, NodeVisitorAbstract, ParserFactory, PrettyPrinter, Error};
use PhpParser\NodeVisitor\CloningVisitor;

class DocBlockAdder extends NodeVisitorAbstract {
    public function enterNode(Node $node) {
        if ($node instanceof Node\Stmt\ClassMethod) {
            $comments = $node->getAttribute('comments');
            if (empty($comments)) {
                $params = [];
                foreach ($node->getParams() as $param) {
                    $type = $param->type ? $this->getTypeString($param->type) : 'mixed';
                    $paramName = '$' . $param->var->name;
                    if ($param->default) {
                        $paramName .= ' = ' . $this->getNodeString($param->default);
                    }
                    $params[] = " * @param " . $type . " " . $paramName;
                }
                $returnType = $node->getReturnType() ? $this->getTypeString($node->getReturnType()) : 'void';
                $doc = "/**\n";
                $doc .= " * " . $node->name->name . " function\n";
                $doc .= " *\n";
                if (!empty($params)) {
                    $doc .= implode("\n", $params) . "\n";
                }
                $doc .= " * @return " . $returnType . "\n";
                $doc .= " */";
                $node->setAttribute('comments', [new \PhpParser\Comment\Doc($doc)]);
            }
        }
    }

    private function getTypeString(Node $type) {
        if ($type instanceof Node\NullableType) {
            return '?' . $type->type->toString();
        }
        return $type->toString();
    }

    private function getNodeString(Node $node) {
        if ($node instanceof Node\Expr\ConstFetch) {
            return $node->name->toString();
        } elseif ($node instanceof Node\Scalar) {
            return $node->value;
        }
        return $node->getType();
    }
}

$directory = new RecursiveDirectoryIterator('src/Controller');
$iterator = new RecursiveIteratorIterator($directory);
$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$parser = (new ParserFactory())->createForHostVersion();
$traverser = new NodeTraverser();
$traverser->addVisitor(new CloningVisitor());
$traverser->addVisitor(new DocBlockAdder());
$printer = new PrettyPrinter\Standard();

foreach ($regex as $file) {
    $filePath = $file[0];
    try {
        $code = file_get_contents($filePath);
        $oldStmts = $parser->parse($code);
        $oldTokens = $parser->getTokens();

        $newStmts = $traverser->traverse($oldStmts);

        // Aqui você pode modificar $newStmts se necessário

        $newCode = $printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
        file_put_contents($filePath, $newCode);
    } catch (Error $e) {
        echo 'Parse Error: ', $e->getMessage();
    }
}
