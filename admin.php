
<td>
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">

        <!-- SAVE BOOKING STATUS -->
        <form method="POST" class="status-form"
              style="display: flex; align-items: center; gap: 8px;">

            <input type="hidden" name="action" value="update_status">

            <input type="hidden" name="booking_id"
                   value="<?= (int)$row["booking_id"] ?>">

            <select name="booking_status">
                <?php foreach (["Pending", "Approved", "Rejected", "Completed"] as $status): ?>
                    <option value="<?= e($status) ?>"
                        <?= ($row["booking_status"] === $status ? "selected" : "") ?>>
                        <?= e($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="btn primary small" type="submit">
                Save
            </button>
        </form>

        <!-- DELETE BOOKING -->
        <form method="POST"
              onsubmit="return confirm('Are you sure you want to delete this booking?');"
              style="display: inline-block;">

            <input type="hidden" name="action" value="delete_booking">

            <input type="hidden" name="booking_id"
                   value="<?= (int)$row["booking_id"] ?>">

            <button type="submit"
                    class="btn small"
                    style="background-color: #dc2626 !important;
                           color: #ffffff !important;
                           border: none;
                           padding: 10px 16px;
                           cursor: pointer;">
                Delete
            </button>

        </form>

    </div>
</td>