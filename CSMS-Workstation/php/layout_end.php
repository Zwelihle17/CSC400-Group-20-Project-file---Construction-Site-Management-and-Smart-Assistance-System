  </div><!-- /sections wrapper -->
</div><!-- /main -->

<!-- MESSAGE VIEWER MODAL -->
<div class="msg-overlay" id="msgViewOverlay" onclick="if(event.target===this)closeMsgView()">
  <div class="msg-modal">
    <div class="msg-modal-head">
      <div>
        <div class="msg-modal-subject" id="msgSubjectView"></div>
        <div class="msg-modal-meta"    id="msgMetaView"></div>
      </div>
      <button class="msg-close" onclick="closeMsgView()">×</button>
    </div>
    <div class="msg-modal-body" id="msgBodyView"></div>
    <!-- Download button — shown only when message has an attachment -->
    <div id="msgAttachWrap" style="padding:0 22px 18px;display:none;">
      <a id="msgDownloadBtn" class="msg-download-btn" href="#" download>
        <?= icon('download','15') ?> Download Attachment
      </a>
    </div>
  </div>
</div>

<!-- COMPOSE MODAL -->
<div class="compose-overlay" id="composeOverlay" onclick="if(event.target===this)closeCompose()">
  <div class="compose-modal">
    <div class="compose-head">
      <span class="compose-title"><?= icon('send-2','16') ?> New Message</span>
      <button class="compose-close" onclick="closeCompose()">×</button>
    </div>
    <div class="compose-body">
      <div class="form-group">
        <label class="f-label">To</label>
        <select class="f-select" id="cmpReceiver">
          <option value="">— Select Recipient —</option>
          <?php foreach ($allUsers as $u): if ($u['id']==$uid) continue; ?>
          <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= $u['employee_id'] ?> · <?= str_replace('_',' ',$u['role']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="f-label">Subject</label>
        <input class="f-input" type="text" id="cmpSubject" placeholder="Message subject">
      </div>
      <div class="form-group">
        <label class="f-label">Message</label>
        <textarea class="f-textarea" id="cmpBody" rows="4" placeholder="Type your message…"></textarea>
      </div>

      <!-- File attachment area -->
      <div class="form-group">
        <label class="f-label">Attachment (optional)</label>
        <div class="attach-bar" onclick="document.getElementById('fileInput').click()">
          <?= icon('upload','16') ?>
          <span class="attach-bar-text"><span>Click to attach</span> a document, PDF, image, or spreadsheet</span>
        </div>
        <input type="file" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.txt,.zip">
        <div id="fileChipWrap"></div>
      </div>
    </div>
    <div class="compose-foot">
      <button class="btn btn-outline" onclick="closeCompose()">Cancel</button>
      <button class="btn btn-amber"   onclick="sendMessage()"><?= icon('send-2','14') ?> Send</button>
    </div>
  </div>
</div>

<script src="../js/dashboard.js"></script>
<script src="../js/ai.js"></script>
</body>
</html>
