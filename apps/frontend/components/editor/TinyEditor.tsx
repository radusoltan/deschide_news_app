'use client';

import { useRef, useEffect } from 'react';
import { Editor } from '@tinymce/tinymce-react';

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
  const editorRef = useRef<any>(null);
  const initialValueRef = useRef(initialValue);
  const imageListRef = useRef(imageList);

  // Update imageListRef when imageList changes
  useEffect(() => {
    imageListRef.current = imageList;
  }, [imageList]);

  // Only update initial value on mount, not on every re-render
  useEffect(() => {
    initialValueRef.current = initialValue;
  }, []);

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
          'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | blockquote | link image media table | charmap anchor | searchreplace visualblocks fullscreen | code preview help'
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
          'preview',
          'help',
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
      initialValue={initialValueRef.current}
      onEditorChange={(content, editor) => {
        if (onChange) {
          onChange(editor.getContent());
        }
      }}
    />
  );
}
