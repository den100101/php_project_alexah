<?php
/*
 * crud.php
 * Reusable CRUD for Courses, Term, School Year, Section and Year Level.
 *
 * Requirements:
 * - db.php provides a mysqli connection named $conn.
 * - auth.php provides require_access(), can_edit(), can_search(),
 *   csrf_field(), csrf_check(), current_usertype(), and h().
 * - layout.php provides page_header() and page_footer().
 */

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/layout.php";

/*
 * These table and column names are fixed in the server configuration.
 * Never accept SQL table or column names directly from user input.
 */
function crud_config(string $key): ?array
{
    $configs = [
        'subjects' => [
            'access_key' => 'subjects',
            'title' => 'Subjects',
            'table' => 'tbl_course',
            'fields' => [
                'COURSE_CODE' => [
                    'label' => 'Course Code',
                    'type' => 'text',
                    'required' => true,
                    'max' => 20,
                ],
                'COURSE_NAME' => [
                    'label' => 'Course Name',
                    'type' => 'text',
                    'required' => true,
                    'max' => 100,
                ],
                'UNITS' => [
                    'label' => 'Units',
                    'type' => 'number',
                    'required' => true,
                    'min' => 1,
                    'max' => 100,
                ],
            ],
            'search_columns' => ['COURSE_CODE', 'COURSE_NAME'],
        ],

        'course' => [
            'access_key' => 'subjects',
            'title' => 'Courses',
            'table' => 'tbl_course',
            'fields' => [
                'COURSE_CODE' => [
                    'label' => 'Course Code',
                    'type' => 'text',
                    'required' => true,
                    'max' => 20,
                ],
                'COURSE_NAME' => [
                    'label' => 'Course Name',
                    'type' => 'text',
                    'required' => true,
                    'max' => 100,
                ],
                'UNITS' => [
                    'label' => 'Units',
                    'type' => 'number',
                    'required' => true,
                    'min' => 1,
                    'max' => 100,
                ],
            ],
            'search_columns' => ['COURSE_CODE', 'COURSE_NAME'],
        ],

        'term' => [
            'access_key' => 'term',
            'title' => 'Term',
            'table' => 'tbl_term',
            'fields' => [
                'TERM_NAME' => [
                    'label' => 'Term Name',
                    'type' => 'text',
                    'required' => true,
                    'max' => 100,
                ],
            ],
            'search_columns' => ['TERM_NAME'],
        ],

        'schoolyear' => [
            'access_key' => 'schoolyear',
            'title' => 'School Year',
            'table' => 'tbl_schoolyear',
            'fields' => [
                'SCHOOL_YEAR' => [
                    'label' => 'School Year',
                    'type' => 'text',
                    'required' => true,
                    'max' => 20,
                ],
            ],
            'search_columns' => ['SCHOOL_YEAR'],
        ],

        'section' => [
            'access_key' => 'section',
            'title' => 'Section',
            'table' => 'tbl_section',
            'fields' => [
                'SECTION_NAME' => [
                    'label' => 'Section Name',
                    'type' => 'text',
                    'required' => true,
                    'max' => 100,
                ],
                'YEAR_LEVEL' => [
                    'label' => 'Year Level',
                    'type' => 'text',
                    'required' => false,
                    'max' => 50,
                ],
            ],
            'search_columns' => ['SECTION_NAME', 'YEAR_LEVEL'],
        ],

        'yearlevel' => [
            'access_key' => 'yearlevel',
            'title' => 'Year Level',
            'table' => 'tbl_yearlevel',
            'fields' => [
                'YEAR_LEVEL' => [
                    'label' => 'Year Level Code',
                    'type' => 'text',
                    'required' => true,
                    'max' => 50,
                ],
                'YEARLEVEL_NAME' => [
                    'label' => 'Year Level Name',
                    'type' => 'text',
                    'required' => true,
                    'max' => 50,
                ],
            ],
            'search_columns' => ['YEAR_LEVEL', 'YEARLEVEL_NAME'],
        ],
    ];

    return $configs[strtolower(trim($key))] ?? null;
}

/**
 * Fetch a single record by ID using a prepared statement.
 */
function crud_get_record(mysqli $conn, array $config, int $id): ?array
{
    $table = $config['table'];

    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE ID = ? LIMIT 1");

    if (!$stmt) {
        throw new RuntimeException("Unable to prepare record lookup.");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $record = $result->fetch_assoc() ?: null;

    $stmt->close();

    return $record;
}

/**
 * Render and process the CRUD interface.
 *
 * Existing page files can call:
 * render_crud([
 *     'key' => 'subjects',
 *     'title' => 'Subjects'
 * ]);
 *
 * The actual table and columns are taken from the fixed configuration above.
 */
function render_crud(array $pageConfig): void
{
    global $conn;

    $key = (string)($pageConfig['key'] ?? '');
    $config = crud_config($key);

    if ($config === null) {
        http_response_code(400);
        exit("Invalid CRUD page configuration.");
    }

    require_access($config['access_key']);

    $canEdit = can_edit();
    $canSearch = can_search();

    $message = '';
    $messageType = 'ok';
    $editRecord = null;
    $search = '';

    /*
     * Show messages after a successful POST redirect.
     */
    if (isset($_GET['saved'])) {
        $message = 'Record saved successfully.';
    } elseif (isset($_GET['deleted'])) {
        $message = 'Record deleted successfully.';
    }

    if (isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
        if ($canEdit) {
            try {
                $editRecord = crud_get_record(
                    $conn,
                    $config,
                    (int)$_GET['edit']
                );

                if (!$editRecord) {
                    $message = 'The requested record was not found.';
                    $messageType = 'err';
                }
            } catch (Throwable $e) {
                error_log('CRUD edit lookup failed: ' . $e->getMessage());
                $message = 'Unable to load the selected record.';
                $messageType = 'err';
            }
        }
    }

    /*
     * Process form submissions.
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$canEdit) {
            http_response_code(403);
            exit("You do not have permission to modify records.");
        }

        csrf_check();

        $action = (string)($_POST['crud_action'] ?? '');
        $recordId = filter_var(
            $_POST['record_id'] ?? 0,
            FILTER_VALIDATE_INT
        );

        try {
            if ($action === 'save') {
                $postedValues = $_POST['values'] ?? [];

                if (!is_array($postedValues)) {
                    throw new InvalidArgumentException(
                        "Invalid form submission."
                    );
                }

                $values = [];

                foreach ($config['fields'] as $column => $field) {
                    $raw = $postedValues[$column] ?? '';

                    if (!is_scalar($raw)) {
                        throw new InvalidArgumentException(
                            "Invalid value for " . $field['label'] . "."
                        );
                    }

                    $value = trim((string)$raw);

                    if ($field['required'] && $value === '') {
                        throw new InvalidArgumentException(
                            $field['label'] . " is required."
                        );
                    }

                    if ($value === '') {
                        $values[$column] = null;
                        continue;
                    }

                    if (
                        isset($field['max']) &&
                        mb_strlen($value) > $field['max']
                    ) {
                        throw new InvalidArgumentException(
                            $field['label'] . " is too long."
                        );
                    }

                    if ($field['type'] === 'number') {
                        $number = filter_var(
                            $value,
                            FILTER_VALIDATE_INT
                        );

                        if ($number === false) {
                            throw new InvalidArgumentException(
                                $field['label'] . " must be a whole number."
                            );
                        }

                        if (
                            isset($field['min']) &&
                            $number < $field['min']
                        ) {
                            throw new InvalidArgumentException(
                                $field['label'] . " is below the minimum."
                            );
                        }

                        if (
                            isset($field['max']) &&
                            $number > $field['max']
                        ) {
                            throw new InvalidArgumentException(
                                $field['label'] . " exceeds the maximum."
                            );
                        }

                        $values[$column] = $number;
                    } else {
                        $values[$column] = $value;
                    }
                }

                $table = $config['table'];
                $columns = array_keys($values);

                if ($recordId && $recordId > 0) {
                    // Update an existing record.
                    $setParts = [];

                    foreach ($columns as $column) {
                        $setParts[] = "`$column` = ?";
                    }

                    $sql = "UPDATE `$table` SET "
                        . implode(', ', $setParts)
                        . " WHERE ID = ?";

                    $stmt = $conn->prepare($sql);

                    if (!$stmt) {
                        throw new RuntimeException(
                            "Unable to prepare record update."
                        );
                    }

                    $types = '';
                    $params = [];

                    foreach ($columns as $column) {
                        if ($column === 'UNITS') {
                            $types .= 'i';
                        } else {
                            $types .= 's';
                        }

                        $params[] = $values[$column];
                    }

                    $types .= 'i';
                    $params[] = (int)$recordId;

                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Insert a new record.
                    $columnSql = implode(
                        ', ',
                        array_map(
                            fn($column) => "`$column`",
                            $columns
                        )
                    );

                    $placeholders = implode(
                        ', ',
                        array_fill(0, count($columns), '?')
                    );

                    $sql = "INSERT INTO `$table` ($columnSql) "
                        . "VALUES ($placeholders)";

                    $stmt = $conn->prepare($sql);

                    if (!$stmt) {
                        throw new RuntimeException(
                            "Unable to prepare record insertion."
                        );
                    }

                    $types = '';
                    $params = [];

                    foreach ($columns as $column) {
                        $types .= $column === 'UNITS' ? 'i' : 's';
                        $params[] = $values[$column];
                    }

                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $stmt->close();
                }

                header(
                    "Location: " . basename($_SERVER['PHP_SELF']) . "?saved=1"
                );
                exit();
            }

            if ($action === 'delete') {
                if (!$recordId || $recordId < 1) {
                    throw new InvalidArgumentException(
                        "Invalid record ID."
                    );
                }

                $table = $config['table'];

                $stmt = $conn->prepare(
                    "DELETE FROM `$table` WHERE ID = ?"
                );

                if (!$stmt) {
                    throw new RuntimeException(
                        "Unable to prepare record deletion."
                    );
                }

                $stmt->bind_param("i", $recordId);
                $stmt->execute();

                $deleted = $stmt->affected_rows;
                $stmt->close();

                if ($deleted < 1) {
                    throw new RuntimeException(
                        "The record was not found or could not be deleted."
                    );
                }

                header(
                    "Location: " . basename($_SERVER['PHP_SELF']) . "?deleted=1"
                );
                exit();
            }

            throw new InvalidArgumentException("Unknown CRUD action.");
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $messageType = 'err';
        } catch (mysqli_sql_exception $e) {
            error_log('CRUD database operation failed: ' . $e->getMessage());

            if ((int)$e->getCode() === 1062) {
                $message = 'A record with the same unique value already exists.';
            } else {
                $message = 'Database operation failed. Check the submitted data and try again.';
            }

            $messageType = 'err';
        } catch (Throwable $e) {
            error_log('CRUD operation failed: ' . $e->getMessage());
            $message = 'Unable to complete the operation.';
            $messageType = 'err';
        }
    }

    /*
     * Search is available only to roles permitted by can_search().
     * Student searches are ignored on the server.
     */
    if ($canSearch) {
        $search = trim((string)($_GET['q'] ?? ''));

        if (mb_strlen($search) > 100) {
            $search = mb_substr($search, 0, 100);
        }
    }

    $records = [];
    $queryError = false;

    try {
        $table = $config['table'];

        if ($search !== '' && $canSearch) {
            $whereParts = [];

            foreach ($config['search_columns'] as $column) {
                $whereParts[] = "`$column` LIKE ?";
            }

            $sql = "SELECT * FROM `$table` WHERE "
                . implode(' OR ', $whereParts)
                . " ORDER BY ID DESC";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                throw new RuntimeException("Unable to prepare search.");
            }

            $pattern = '%' . $search . '%';
            $types = str_repeat('s', count($config['search_columns']));
            $params = array_fill(
                0,
                count($config['search_columns']),
                $pattern
            );

            $stmt->bind_param($types, ...$params);
            $stmt->execute();

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }

            $stmt->close();
        } else {
            $result = $conn->query(
                "SELECT * FROM `$table` ORDER BY ID DESC"
            );

            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        }
    } catch (Throwable $e) {
        error_log('CRUD record retrieval failed: ' . $e->getMessage());
        $message = 'Unable to retrieve records from the database.';
        $messageType = 'err';
        $queryError = true;
    }

    page_header($config['title'], $config['access_key']);
?>

    <h1><?php echo h($config['title']); ?></h1>

    <?php if ($message !== ''): ?>
        <div class="msg <?php echo h($messageType); ?>">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($canEdit): ?>
        <section class="crud-form">
            <h2>
                <?php echo $editRecord ? 'Edit Record' : 'Add Record'; ?>
            </h2>

            <form method="POST" action="">
                <?php echo csrf_field(); ?>

                <input type="hidden" name="crud_action" value="save">

                <input
                    type="hidden"
                    name="record_id"
                    value="<?php
                            echo h($editRecord['ID'] ?? '');
                            ?>">

                <?php foreach ($config['fields'] as $column => $field): ?>
                    <div class="form-row">
                        <label for="field_<?php echo h($column); ?>">
                            <?php echo h($field['label']); ?>
                            <?php if ($field['required']): ?>
                                <span aria-hidden="true">*</span>
                            <?php endif; ?>
                        </label>

                        <input
                            id="field_<?php echo h($column); ?>"
                            type="<?php echo h($field['type']); ?>"
                            name="values[<?php echo h($column); ?>]"
                            value="<?php
                                    echo h($editRecord[$column] ?? '');
                                    ?>"
                            <?php if ($field['required']): ?>required<?php endif; ?>
                            <?php if (isset($field['max'])): ?>
                            maxlength="<?php echo (int)$field['max']; ?>"
                            <?php endif; ?>
                            <?php if (isset($field['min'])): ?>
                            min="<?php echo (int)$field['min']; ?>"
                            <?php endif; ?>
                            <?php if ($field['type'] === 'number'): ?>
                            step="1"
                            <?php endif; ?>>
                    </div>
                <?php endforeach; ?>

                <button type="submit">
                    <?php echo $editRecord ? 'Update Record' : 'Add Record'; ?>
                </button>

                <?php if ($editRecord): ?>
                    <a
                        href="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>"
                        class="btn btn-gray">Cancel</a>
                <?php endif; ?>
            </form>
        </section>
    <?php else: ?>
        <p class="note">You have view-only access to this page.</p>
    <?php endif; ?>

    <?php if ($canSearch): ?>
        <form method="GET" action="" class="crud-search">
            <label for="search">Search records</label>

            <input
                type="text"
                id="search"
                name="q"
                value="<?php echo h($search); ?>"
                placeholder="Enter search keyword..."
                maxlength="100">

            <button type="submit">Search</button>

            <?php if ($search !== ''): ?>
                <a
                    href="<?php echo h(basename($_SERVER['PHP_SELF'])); ?>"
                    class="btn btn-gray">Clear</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <h2>Records</h2>

    <?php if (!$queryError): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>

                        <?php foreach ($config['fields'] as $field): ?>
                            <th><?php echo h($field['label']); ?></th>
                        <?php endforeach; ?>

                        <?php if ($canEdit): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td
                                colspan="<?php
                                            echo count($config['fields'])
                                                + 1
                                                + ($canEdit ? 1 : 0);
                                            ?>">No records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo h($record['ID']); ?></td>

                                <?php foreach ($config['fields'] as $column => $field): ?>
                                    <td>
                                        <?php
                                        echo h($record[$column] ?? '');
                                        ?>
                                    </td>
                                <?php endforeach; ?>

                                <?php if ($canEdit): ?>
                                    <td>
                                        <a
                                            class="btn btn-gray"
                                            href="<?php
                                                    echo h(basename($_SERVER['PHP_SELF']));
                                                    ?>?edit=<?php
                                                            echo (int)$record['ID'];
                                                            ?>">Edit</a>

                                        <form
                                            method="POST"
                                            action=""
                                            class="inline"
                                            onsubmit="return confirm('Are you sure you want to delete this record?');">
                                            <?php echo csrf_field(); ?>

                                            <input
                                                type="hidden"
                                                name="crud_action"
                                                value="delete">

                                            <input
                                                type="hidden"
                                                name="record_id"
                                                value="<?php
                                                        echo (int)$record['ID'];
                                                        ?>">

                                            <button
                                                type="submit"
                                                class="btn-red">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php
    page_footer();
}
