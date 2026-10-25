<?php
/* layout.php - shared header / navigation / footer */

require_once __DIR__ . "/auth.php";

function page_header($title, $activeKey = '')
{
    $username = $_SESSION['USERNAME'] ?? '';
    $usertype = current_usertype();

    // IMAGE_PATH will be loaded into the session by login.php.
    $profileImage = $_SESSION['IMAGE_PATH'] ?? '';

    // Accept only local relative image paths.
    if (
        !is_string($profileImage) ||
        $profileImage === '' ||
        str_contains($profileImage, '..') ||
        str_contains($profileImage, '://') ||
        str_starts_with($profileImage, '/')
    ) {
        $profileImage = '';
    }

    $avatarLetter = strtoupper(substr($username, 0, 1));
    if ($avatarLetter === '') {
        $avatarLetter = '?';
    }
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo h($title); ?></title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>

    <body>

        <nav class="nav">
            <a
                href="dashboard.php"
                class="nav-link <?php echo $activeKey === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>

            <?php foreach (PAGES as $key => $info): ?>
                <?php if (can_access($key)): ?>
                    <a
                        href="<?php echo h($info[1]); ?>"
                        class="nav-link <?php echo $activeKey === $key ? 'active' : ''; ?>"><?php echo h($info[0]); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <span class="nav-spacer"></span>

            <div class="nav-user">
                <?php if ($profileImage !== ''): ?>
                    <img
                        class="nav-avatar"
                        src="<?php echo h($profileImage); ?>"
                        alt="Profile picture"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span class="nav-avatar-fallback" style="display:none;">
                        <?php echo h($avatarLetter); ?>
                    </span>
                <?php else: ?>
                    <span class="nav-avatar-fallback">
                        <?php echo h($avatarLetter); ?>
                    </span>
                <?php endif; ?>

                <span class="nav-user-info">
                    <strong><?php echo h($username); ?></strong>
                    <small><?php echo h($usertype); ?></small>
                </span>
            </div>

            <a href="logout.php" class="nav-link logout-link">Logout</a>
        </nav>

        <main class="wrap">
        <?php
    }

    function page_footer()
    {
        ?>
        </main>
    </body>

    </html>
<?php
    }
?>