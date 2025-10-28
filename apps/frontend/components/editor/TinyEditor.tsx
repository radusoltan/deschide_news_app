'use client';

import { useRef } from 'react';
import { Editor } from '@tinymce/tinymce-react';

interface TinyEditorProps {
  initialValue?: string;
  onChange?: (html: string) => void;
  height?: number;
}

export default function TinyEditor({
  initialValue = '',
  onChange,
  height = 600
}: TinyEditorProps) {
  const editorRef = useRef<any>(null);

  return (
    <Editor
      licenseKey="gpl"
      // Use local TinyMCE (self-hosted)
      tinymceScriptSrc="/tinymce/tinymce.min.js"
      // Editor runs only in browser
      onInit={(evt, editor) => (editorRef.current = editor)}
      init={{
        // Base URL for plugins/skin
        base_url: '/tinymce',
        suffix: '.min',

        // UI Configuration
        height: height,
        min_height: height,
        menubar: 'file edit view insert format tools table help',
        toolbar:
          'undo redo | blocks | bold italic underline strikethrough | ' +
          'alignleft aligncenter alignright alignjustify | ' +
          'bullist numlist outdent indent | link image table | code',

        // Plugins (ensure they exist in /public/tinymce/plugins)
        plugins: 'code link lists image table',

        // Resize options
        resize: true,

        // Content & URL policy
        convert_urls: false,

        // Dark mode support
        skin: 'oxide',
        content_css: 'default',
      }}
      initialValue={initialValue}
      onEditorChange={(content) => onChange?.(content)}
    />
  );
}
