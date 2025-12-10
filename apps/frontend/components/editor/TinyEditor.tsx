'use client';

import { useRef, useEffect, useState } from 'react';
import { Editor } from '@tinymce/tinymce-react';
import type { Editor as TinyMCEEditor } from 'tinymce';

interface TinyEditorProps {
  initialValue?: string;
  onChange?: (html: string) => void;
  height?: number;
  imageList?: Array<{ title: string; value: string }>;
}

export default function TinyEditor({
  initialValue = '',
  onChange,
  height = 500,
  imageList = []
}: TinyEditorProps) {
  const editorRef = useRef<TinyMCEEditor | null>(null);
  const imageListRef = useRef(imageList);
  // Store initial value in state to avoid ref access during render
  const [editorInitialValue] = useState(initialValue);

  // Update imageListRef when imageList changes
  useEffect(() => {
    imageListRef.current = imageList;
  }, [imageList]);

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
        menubar: false,

        // Toolbar Configuration (two rows for better visibility)
        toolbar_mode: 'wrap',
        toolbar: [
          'undo redo | blocks fontsize | bold italic underline strikethrough | forecolor backcolor | removeformat',
          'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | blockquote | link image media table | charmap anchor | searchreplace visualblocks fullscreen  code '
        ],

        // Plugins (from example)
        plugins: [
          'advlist',
          'autolink',
          'code',
          'lists',
          'link',
          'image',
          'charmap',
          'anchor',
          'searchreplace',
          'visualblocks',
          'fullscreen',
          'insertdatetime',
          'media',
          'table',
          'wordcount'
        ],

        // Image configuration
        image_advtab: true,
        image_list: (success: (data: Array<{ title: string; value: string }>) => void) => {
          success(imageListRef.current);
        },

        // Resize options
        resize: true,

        // Content & URL policy
        convert_urls: false,

        // Dark mode support
        skin: 'oxide',
        content_css: 'default',
      }}
      initialValue={editorInitialValue}
      onEditorChange={(content, editor) => {
        if (onChange) {
          onChange(editor.getContent());
        }
      }}
    />
  );
}
