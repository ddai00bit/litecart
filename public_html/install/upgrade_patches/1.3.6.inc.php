<?php

  $deleted_files = [
    FS_DIR_APP . 'ext/jquery/jquery-1.12.3.min.js',
    FS_DIR_APP . 'ext/jquery/jquery-migrate-1.3.0.min.js',
  ];

  foreach ($deleted_files as $pattern) {
    if (!file_delete($pattern)) {
      die('<span class="error">[Error]</span></p>');
    }
  }
