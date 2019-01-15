<!DOCTYPE html>
<!--
The MIT License

Copyright 2019 Eric.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
-->
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>


        <script type="text/javascript" src="../js/jquery-3.1.1.min.js"></script>

        <script src="../js/tuiEditorSupport.js"></script>
        <script src="../js/tui-editor-Editor.min.js"></script>

        <link rel="stylesheet" href="css/tui-editor/tui-editor.min.css">
        <link rel="stylesheet" href="css/tui-editor/tui-editor-contents-min.css">
        <link rel="stylesheet" href="css/tui-editor/codemirror.css">


        <script>

    $(document).ready(function () {

      var editor =  new tui.Editor({
        el: document.querySelector('#editSection'),
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        initialValue: 'Hello there',
        usageStatistics: false,
        height: '300px',
        toolbarItems: [
          'heading',
          'bold',
          'italic',
          'divider',
          'hr',
          'quote',
          'divider',
          'ul',
          'ol',
          'task',
          'indent',
          'outdent',
        ]
      });

    $('#RelSelect').change(function () {
       editor.insertText('Inserted!');
      });


    });
</script>
    </head>
    <body>
        <div class="code-html">
            <select id='RelSelect'><option value="1">One</option><option value="2">Two</option></select>
        <div id="editSection"></div>

        </div>
    </body>
</html>
