<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Cedente — Documentação</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui.css" crossorigin="anonymous">
    <style>
        html { box-sizing: border-box; }
        *, *::before, *::after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }

        #frontend-guide {
            max-width: 1460px;
            margin: 0 auto;
            padding: 0 20px 40px;
            font-family: sans-serif;
        }

        #frontend-guide .guide-block {
            background: #fff;
            border: 1px solid rgba(59, 65, 81, 0.3);
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            margin-top: 24px;
        }

        #frontend-guide .guide-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid rgba(59, 65, 81, 0.2);
            background: rgba(0, 0, 0, 0.02);
        }

        #frontend-guide .guide-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #3b4151;
        }

        #frontend-guide .guide-header p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #6b7280;
        }

        #frontend-guide details.guide-section {
            border-bottom: 1px solid rgba(59, 65, 81, 0.15);
        }

        #frontend-guide details.guide-section:last-child {
            border-bottom: none;
        }

        #frontend-guide details.guide-section > summary {
            list-style: none;
            cursor: pointer;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #3b4151;
            background: #fff;
            user-select: none;
        }

        #frontend-guide details.guide-section > summary::-webkit-details-marker {
            display: none;
        }

        #frontend-guide details.guide-section > summary::before {
            content: '▸';
            display: inline-block;
            width: 16px;
            margin-right: 6px;
            color: #6b7280;
            transition: transform 0.15s ease;
        }

        #frontend-guide details.guide-section[open] > summary::before {
            transform: rotate(90deg);
        }

        #frontend-guide details.guide-section > summary:hover {
            background: rgba(0, 0, 0, 0.02);
        }

        #frontend-guide .guide-body {
            padding: 0 20px 16px 42px;
            font-size: 14px;
            line-height: 1.55;
            color: #3b4151;
        }

        #frontend-guide .guide-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            font-size: 13px;
        }

        #frontend-guide .guide-body th,
        #frontend-guide .guide-body td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }

        #frontend-guide .guide-body th {
            background: #f9fafb;
            font-weight: 600;
        }

        #frontend-guide .guide-body pre {
            background: #f6f8fa;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 12px;
            overflow-x: auto;
            font-size: 12px;
        }

        #frontend-guide .guide-body code {
            background: #f3f4f6;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 12px;
        }

        #frontend-guide .guide-body pre code {
            background: none;
            padding: 0;
        }

        #frontend-guide .guide-body h3 {
            margin: 16px 0 8px;
            font-size: 15px;
        }

        #frontend-guide .guide-body ul {
            margin: 8px 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<div id="frontend-guide" hidden></div>

<script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-bundle.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/swagger-ui-dist@5.11.0/swagger-ui-standalone-preset.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/js-yaml@4.1.0/dist/js-yaml.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/marked@12.0.2/marked.min.js" crossorigin="anonymous"></script>
<script>
    (function () {
        var openapiUrl = @json(url('/docs/cedente/openapi.yaml'));

        function renderFrontendGuide(guide) {
            if (!guide || !guide.sections || !guide.sections.length) {
                return;
            }

            var container = document.getElementById('frontend-guide');
            var block = document.createElement('div');
            block.className = 'guide-block';

            var header = document.createElement('div');
            header.className = 'guide-header';
            header.innerHTML = '<div><h2>' + escapeHtml(guide.title || 'Guia para o front-end') + '</h2>' +
                (guide.description ? '<p>' + escapeHtml(guide.description) + '</p>' : '') + '</div>';
            block.appendChild(header);

            guide.sections.forEach(function (section) {
                var details = document.createElement('details');
                details.className = 'guide-section';
                details.id = 'guide-' + (section.id || section.title);

                var summary = document.createElement('summary');
                summary.textContent = section.title || 'Seção';
                details.appendChild(summary);

                var body = document.createElement('div');
                body.className = 'guide-body';
                body.innerHTML = marked.parse(section.content || '', { breaks: true, gfm: true });
                details.appendChild(body);

                block.appendChild(details);
            });

            container.appendChild(block);
            container.hidden = false;

            var swaggerWrapper = document.querySelector('#swagger-ui .wrapper');
            if (swaggerWrapper) {
                swaggerWrapper.appendChild(container);
            } else {
                document.getElementById('swagger-ui').insertAdjacentElement('afterend', container);
            }
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function loadFrontendGuide() {
            fetch(openapiUrl)
                .then(function (response) { return response.text(); })
                .then(function (yamlText) {
                    var spec = jsyaml.load(yamlText);
                    renderFrontendGuide(spec['x-frontend-guide']);
                })
                .catch(function (err) {
                    console.warn('Não foi possível carregar o guia do front-end:', err);
                });
        }

        window.onload = function () {
            window.ui = SwaggerUIBundle({
                url: openapiUrl,
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                layout: 'StandaloneLayout',
                onComplete: loadFrontendGuide,
            });
        };
    })();
</script>
</body>
</html>
