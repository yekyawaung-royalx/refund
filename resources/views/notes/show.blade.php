<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laravel Queue Notes</title>
    
    <!-- GitHub Markdown Dark CSS -->
    <link href="https://cdn.jsdelivr.net/npm/github-markdown-css@5.2.0/github-markdown-dark.min.css" rel="stylesheet">

    <!-- Prism.js CSS for dark theme -->
    <link href="https://cdn.jsdelivr.net/npm/prismjs/themes/prism-tomorrow.css" rel="stylesheet" />

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs/prism.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs/components/prism-php.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs/components/prism-bash.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs/components/prism-ini.min.js"></script>

    <style>
        body {
            background-color: #0d1117;
            color: #c9d1d9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            padding: 2rem;
        }

        .markdown-body {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
            background: #16221d;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }

        pre {
    background-color: #161b22; /* dark background */
    color: #c9d1d9;           /* light text */
    padding: 1rem;
    border-radius: 8px;
    overflow-x: auto;
    font-family: 'Fira Code', monospace;
    font-size: 0.9rem;
    line-height: 1.4;
    margin: 1rem 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.5);
}

        code {
    background-color: #16221c;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Fira Code', monospace;
    color: #c9d1d9;
}

/* Optional: highlight specific language with Prism.js */
.language-ini {
    color: #9cdcfe; /* light blue for ini keys */
}

/* Add subtle line numbers (optional) */
pre[class*="language-"] {
    counter-reset: linenumber;
}

pre[class*="language-"] code {
    display: block;
}

pre[class*="language-"] code span {
    display: block;
    counter-increment: linenumber;
}

pre[class*="language-"] code span::before {
    content: counter(linenumber);
    display: inline-block;
    width: 2em;
    margin-right: 1em;
    text-align: right;
    color: #6e7681;
}

        a {
            color: #58a6ff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
        .markdown-body .highlight pre, .markdown-body pre{
            background-color: #043934;
        }
    </style>
</head>
<body>
    <article class="markdown-body" id="markdown-content">
        Loading...
    </article>

    <script>
        // Pass Markdown content from backend
        const mdContent = {!! $content !!};

        // Parse markdown
        const html = marked.parse(mdContent);

        // Insert HTML
        document.getElementById('markdown-content').innerHTML = html;

        // Highlight all code blocks
        document.querySelectorAll('pre code').forEach((block) => {
            Prism.highlightElement(block);
        });
    </script>
</body>
</html>