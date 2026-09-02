<div class="cancel-modal" id="cancel-order-modal" aria-hidden="true">
    <div class="cancel-modal-backdrop" data-close-cancel-modal></div>
    <section class="cancel-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cancel-modal-title">
        <button class="cancel-modal-close" type="button" data-close-cancel-modal aria-label="Đóng">×</button>
        <span class="cancel-modal-eyebrow">HỦY ĐƠN HÀNG</span>
        <h2 id="cancel-modal-title">Cho chúng tôi biết lý do</h2>
        <p>Lý do của bạn giúp cửa hàng cải thiện trải nghiệm mua sắm.</p>

        <div class="cancel-reason-options">
            <button type="button" data-cancel-reason="Tôi muốn thay đổi sản phẩm hoặc phân loại hàng">Muốn đổi sản phẩm</button>
            <button type="button" data-cancel-reason="Thông tin hoặc địa chỉ giao hàng chưa chính xác">Sai thông tin giao hàng</button>
            <button type="button" data-cancel-reason="Tôi không còn nhu cầu mua sản phẩm này">Không còn nhu cầu</button>
        </div>

        <form id="cancel-order-form" method="POST">
            @csrf
            @method('PATCH')
            <label for="cancellation-reason">Lý do hủy đơn <b>*</b></label>
            <textarea id="cancellation-reason" name="cancellation_reason" rows="4" minlength="5" maxlength="500" placeholder="Nhập lý do cụ thể (ít nhất 5 ký tự)..." required></textarea>
            <div class="cancel-character-count"><span id="cancel-reason-count">0</span>/500</div>
            <div class="cancel-modal-actions">
                <button class="cancel-keep-button" type="button" data-close-cancel-modal>Giữ lại đơn</button>
                <button class="cancel-confirm-button" type="submit">Xác nhận hủy</button>
            </div>
        </form>
    </section>
</div>

<style>
    .cancel-modal{position:fixed;inset:0;z-index:1000;display:none;align-items:center;justify-content:center;padding:20px}.cancel-modal.open{display:flex}.cancel-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.58);backdrop-filter:blur(2px)}.cancel-modal-panel{position:relative;width:min(520px,100%);padding:25px;background:#fff;border-radius:12px;box-shadow:0 24px 60px rgba(15,23,42,.28);color:#334155}.cancel-modal-close{position:absolute;top:12px;right:14px;width:34px;height:34px;border:0;border-radius:50%;background:#f1f5f9;color:#64748b;font-size:23px;cursor:pointer}.cancel-modal-eyebrow{color:#dc2626;font-size:11px;font-weight:800;letter-spacing:.1em}.cancel-modal-panel h2{margin:6px 35px 7px 0;color:#1e293b;font-size:21px}.cancel-modal-panel>p{margin:0 0 17px;color:#64748b;font-size:13px}.cancel-reason-options{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px}.cancel-reason-options button{padding:7px 10px;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#475569;font-size:12px;cursor:pointer}.cancel-reason-options button:hover,.cancel-reason-options button.selected{border-color:#ef4444;background:#fef2f2;color:#b91c1c}.cancel-modal form label{display:block;margin-bottom:7px;color:#334155;font-size:13px;font-weight:800}.cancel-modal form label b{color:#dc2626}.cancel-modal textarea{display:block;width:100%;padding:11px 12px;border:1px solid #cbd5e1;border-radius:7px;outline:0;resize:vertical;font:14px Arial,sans-serif;color:#1e293b}.cancel-modal textarea:focus{border-color:#ef4444;box-shadow:0 0 0 3px #fee2e2}.cancel-character-count{text-align:right;margin-top:5px;color:#94a3b8;font-size:11px}.cancel-modal-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:15px}.cancel-modal-actions button{min-height:40px;padding:9px 16px;border-radius:6px;font-weight:800;cursor:pointer}.cancel-keep-button{border:1px solid #cbd5e1;background:#fff;color:#475569}.cancel-confirm-button{border:1px solid #dc2626;background:#dc2626;color:#fff}.cancel-confirm-button:hover{background:#b91c1c}
    body.cancel-modal-open{overflow:hidden}
</style>

<script>
    (function () {
        const modal = document.getElementById('cancel-order-modal');
        const form = document.getElementById('cancel-order-form');
        const reason = document.getElementById('cancellation-reason');
        const count = document.getElementById('cancel-reason-count');
        if (!modal || !form || !reason || !count) return;

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('cancel-modal-open');
        }

        document.querySelectorAll('[data-open-cancel-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                form.action = button.dataset.cancelAction;
                reason.value = '';
                count.textContent = '0';
                modal.querySelectorAll('[data-cancel-reason]').forEach(option => option.classList.remove('selected'));
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('cancel-modal-open');
                setTimeout(() => reason.focus(), 50);
            });
        });

        modal.querySelectorAll('[data-close-cancel-modal]').forEach(button => button.addEventListener('click', closeModal));
        modal.querySelectorAll('[data-cancel-reason]').forEach(function (button) {
            button.addEventListener('click', function () {
                reason.value = button.dataset.cancelReason;
                count.textContent = reason.value.length;
                modal.querySelectorAll('[data-cancel-reason]').forEach(option => option.classList.remove('selected'));
                button.classList.add('selected');
                reason.focus();
            });
        });
        reason.addEventListener('input', () => count.textContent = reason.value.length);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && modal.classList.contains('open')) closeModal();
        });
    }());
</script>
