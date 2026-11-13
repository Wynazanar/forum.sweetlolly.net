<?php

namespace App\Components;

use App\Services\AuthService;

?>
<main>
  <div class="container">
    <a href="<?= url('/applications') ?>">Заявки</a>
    <?php
      if (AuthService::can('resolve_report')) {
        echo "<a href=". url('/moderation') . ">МОДЕРАЦИЯ</a>";
      }
    ?>

  </div>

</main>