<?php

// Legacy endpoint retained for old bookmarks/forms. Authentication now happens
// only through the hardened login page.
header('Location: login.php', true, 303);
exit();
