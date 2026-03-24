<?php
/**
 * HTML to Markdown converter for plugin-generated Markdown output.
 */
class AIVM_Html_To_Md {

    /** Tags whose content (including children) is discarded entirely. */
    private const SKIP_TAGS = ['script', 'style', 'nav', 'header', 'footer', 'aside'];

    /** Tags that are unwrapped — children are rendered, the tag itself is ignored. */
    private const UNWRAP_TAGS = [
        'html', 'body', 'div', 'span', 'section', 'article',
        'main', 'figure', 'figcaption', 'time', 'address',
    ];

    /** Heading tags mapped to their Markdown prefix. */
    private const HEADING_MAP = [
        'h1' => '#',
        'h2' => '##',
        'h3' => '###',
        'h4' => '####',
        'h5' => '#####',
        'h6' => '######',
    ];

    /**
     * Convert an HTML string to Markdown.
     */
    public function convert(string $html): string {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $output = '';
        foreach ($dom->childNodes as $node) {
            $output .= $this->convert_node($node);
        }

        // Collapse runs of 3+ newlines to a maximum of two.
        $output = preg_replace('/\n{3,}/', "\n\n", $output);

        return trim($output);
    }

    /**
     * Recursively convert a DOM node to Markdown.
     */
    private function convert_node(\DOMNode $node): string {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->textContent;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);

        // Skip entirely — discard tag and all descendants.
        if (in_array($tag, self::SKIP_TAGS, true)) {
            return '';
        }

        // Headings.
        if (isset(self::HEADING_MAP[$tag])) {
            $text = trim($node->textContent);
            if ($text === '') {
                return '';
            }
            return "\n\n" . self::HEADING_MAP[$tag] . ' ' . $text . "\n\n";
        }

        // Paragraph.
        if ($tag === 'p') {
            $inner = trim($this->convert_children($node));
            if ($inner === '') {
                return '';
            }
            return "\n\n" . $inner . "\n\n";
        }

        // Line break.
        if ($tag === 'br') {
            return "\n";
        }

        // Unordered list.
        if ($tag === 'ul') {
            return $this->convert_list($node, false);
        }

        // Ordered list.
        if ($tag === 'ol') {
            return $this->convert_list($node, true);
        }

        // List item (standalone — normally handled inside convert_list, but guard here).
        if ($tag === 'li') {
            return trim($this->convert_children($node));
        }

        // Anchor link.
        if ($tag === 'a') {
            $href = $node->getAttribute('href');
            $text = trim($this->convert_children($node));
            if ($href === '') {
                return $text;
            }
            if ($text === '') {
                return $href;
            }
            return '[' . $text . '](' . $href . ')';
        }

        // Image.
        if ($tag === 'img') {
            $src = $node->getAttribute('src');
            if ($src === '') {
                return '';
            }
            $alt = $node->getAttribute('alt');
            return '![' . $alt . '](' . $src . ')';
        }

        // Bold.
        if ($tag === 'strong' || $tag === 'b') {
            $inner = trim($this->convert_children($node));
            if ($inner === '') {
                return '';
            }
            return '**' . $inner . '**';
        }

        // Italic.
        if ($tag === 'em' || $tag === 'i') {
            $inner = trim($this->convert_children($node));
            if ($inner === '') {
                return '';
            }
            return '*' . $inner . '*';
        }

        // Inline code (not inside <pre>).
        if ($tag === 'code') {
            if ($node->parentNode && strtolower($node->parentNode->nodeName) === 'pre') {
                return $node->textContent;
            }
            $inner = $node->textContent;
            if (trim($inner) === '') {
                return '';
            }
            return '`' . $inner . '`';
        }

        // Preformatted / code block.
        if ($tag === 'pre') {
            $code = '';
            foreach ($node->childNodes as $child) {
                $code .= $child->textContent;
            }
            if (trim($code) === '') {
                return '';
            }
            return "\n\n```\n" . $code . "\n```\n\n";
        }

        // Blockquote.
        if ($tag === 'blockquote') {
            $inner = trim($this->convert_children($node));
            if ($inner === '') {
                return '';
            }
            $lines  = explode("\n", $inner);
            $quoted = implode("\n", array_map(fn($l) => '> ' . $l, $lines));
            return "\n\n" . $quoted . "\n\n";
        }

        // Horizontal rule.
        if ($tag === 'hr') {
            return "\n\n---\n\n";
        }

        // Unwrap — render children only.
        if (in_array($tag, self::UNWRAP_TAGS, true)) {
            return $this->convert_children($node);
        }

        // Default: recurse into children.
        return $this->convert_children($node);
    }

    /**
     * Convert a <ul> or <ol> node to a Markdown list.
     */
    private function convert_list(\DOMNode $node, bool $ordered): string {
        $output  = "\n\n";
        $counter = 1;

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            if (strtolower($child->nodeName) !== 'li') {
                continue;
            }
            $item_text = trim($this->convert_children($child));
            if ($item_text === '') {
                continue;
            }
            $prefix  = $ordered ? $counter . '. ' : '- ';
            $output .= $prefix . $item_text . "\n";
            $counter++;
        }

        return $output . "\n";
    }

    /**
     * Render all child nodes of a given node and return the concatenated string.
     */
    private function convert_children(\DOMNode $node): string {
        $output = '';
        foreach ($node->childNodes as $child) {
            $output .= $this->convert_node($child);
        }
        return $output;
    }
}
