<?php foreach ($puzzles as $p_item): ?>

    <h2><?php echo $p_item['id'] ?></h2>
    <div class="main">
        <?php echo $p_item['description'] ?>
    </div>

<?php endforeach ?>
