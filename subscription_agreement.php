<?php
require_once __DIR__ . '/auth_check.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Force browser to never cache this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

require_once 'config.php';

$installation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$installation = null;
$plan_name = '';
$client_signature = '';

if ($installation_id > 0) {
    // Get installation data with plan name from service_plans table
    $query = "SELECT i.*, u.name as assigned_name, sp.plan_name 
              FROM installations i 
              LEFT JOIN users u ON i.assigned_to = u.userID 
              LEFT JOIN service_plans sp ON i.plan = sp.monthly_fee
              WHERE i.id = $installation_id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $installation = $result->fetch_assoc();
        $plan_name = $installation['plan_name'] ?? 'Plan ₱' . $installation['plan'];
        $contract_duration = $installation['contract_duration'] ?? '12';
        $client_signature = $installation['client_signature'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=900, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Client Subscription Agreement - UBILINK</title>
    <link rel="stylesheet" href="subscription_agreement.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // Force reload if page is restored from mobile back/forward cache
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) window.location.reload(true);
        });
    </script>
    <script>
        // Auto-fill form when page loads
        window.addEventListener('DOMContentLoaded', function() {
            <?php if ($installation): ?>
            // Fill NAP Assignment
            document.getElementById('nap_assign').value = '<?php echo addslashes($installation['nap_assignment'] ?? ''); ?>';
            
            // Fill Account Number
            document.getElementById('act_number').value = '<?php echo str_pad($installation['account_number'], 5, '0', STR_PAD_LEFT); ?>';
            
            // Fill Client Name
            document.getElementById('client-name').value = '<?php echo addslashes($installation['client_name']); ?>';
            
            // Fill Address
            document.getElementById('address').value = '<?php echo addslashes($installation['address']); ?>';
            
            // Fill Email
            document.getElementById('email').value = '<?php echo addslashes($installation['email'] ?? ''); ?>';
            
            // Fill Mobile
            document.getElementById('mobile').value = '<?php echo addslashes($installation['contact_number']); ?>';
            
            // Fill Team In-Charge
            document.getElementById('team-incharge').value = '<?php echo addslashes($installation['assigned_name'] ?? ''); ?>';
            
            // Plan name is already displayed via PHP in the checkbox label
            
            document.getElementById('monthlyfee').value = '<?php echo addslashes($installation['plan']); ?>';
            
            // Fill installation date
            <?php 
            $install_date = '';
            if (isset($installation['installation_date']) && !empty($installation['installation_date']) && $installation['installation_date'] != '0000-00-00') {
                $install_date = date('Y-m-d', strtotime($installation['installation_date']));
            } else {
                $install_date = date('Y-m-d'); // Use today's date
            }
            ?>
            document.getElementById('date-installed').value = '<?php echo $install_date; ?>';
            document.getElementById('date').value = '<?php echo $install_date; ?>';
            
            // Calculate and fill end date (1 month ahead)
            var installDate = new Date('<?php echo $install_date; ?>');
            installDate.setMonth(installDate.getMonth() + 1);
            var endDate = installDate.toISOString().split('T')[0];
            if (document.getElementById('End')) {
                document.getElementById('End').value = endDate;
            }
            
            // Check service type checkboxes
            <?php if ($installation['service_type'] == 'New Client'): ?>
            document.getElementById('new-client').checked = true;
            <?php elseif ($installation['service_type'] == 'Migrate'): ?>
            document.getElementById('migrate').checked = true;
            <?php elseif ($installation['service_type'] == 'Reconnection'): ?>
            document.getElementById('reconnection').checked = true;
            <?php endif; ?>
            <?php endif; ?>
        });
    </script>
    <style>
        /* ── Override external CSS conflicts ── */
        .container {
            max-width: 860px !important;
            margin: 0 auto !important;
            background: #fff !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10) !important;
            padding: 32px 36px 28px !important;
        }
        .signature-section {
            display: flex !important;
            flex-direction: row !important;
            justify-content: flex-start !important;
            gap: 24px !important;
            margin: 28px 0 16px !important;
        }
        .signature-box {
            flex: 1 !important;
            width: auto !important;
            text-align: center !important;
        }
        .signature-line {
            border-bottom: 2px solid #1a1a2e !important;
            margin-top: 0 !important;
            min-height: 80px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-end !important;
            align-items: center !important;
            padding-bottom: 6px !important;
        }
        /* ── Print button ── */
        .btn-print {
            display: block !important;
            width: 100% !important;
            padding: 13px !important;
            background: linear-gradient(135deg, #16a34a, #15803d) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 8px !important;
            font-size: 1rem !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            margin-top: 20px !important;
            box-shadow: 0 3px 12px rgba(22,163,74,0.3) !important;
            text-align: center !important;
            position: static !important;
            float: none !important;
        }
        .btn-back {
            display: block !important;
            width: 100% !important;
            padding: 11px !important;
            background: #6b7280 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 8px !important;
            font-size: 0.95rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            margin-top: 10px !important;
        }
        .btn-signature {
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            color: #fff !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 7px 16px !important;
            font-size: 0.82rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            margin-bottom: 6px !important;
            box-shadow: 0 2px 8px rgba(37,99,235,0.3) !important;
            transform: none !important;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            margin: 0;
            padding: 16px;
            color: #1a1a2e;
        }
        /* ── Header ── */
        .doc-header {
            display: flex;
            align-items: center;
            gap: 18px;
            border-bottom: 3px solid #c0392b;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .doc-header img.logo {
            height: 64px;
            width: auto;
            flex-shrink: 0;
        }
        .doc-header-text {
            flex: 1;
        }
        .doc-header-text .company {
            font-size: 1.25rem;
            font-weight: 800;
            color: #c0392b;
            letter-spacing: 1px;
            margin: 0 0 2px;
        }
        .doc-header-text .doc-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0 0 1px;
        }
        .doc-header-text .doc-subtitle {
            font-size: 0.8rem;
            color: #666;
            margin: 0;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        /* ── Section labels ── */
        .section-label {
            background: #c0392b;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 4px;
            margin: 14px 0 6px;
            display: inline-block;
        }
        /* ── Tables ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
            font-size: 0.82rem;
        }
        table td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: middle;
        }
        table td strong {
            color: #374151;
            font-size: 0.78rem;
        }
        /* ── Inputs ── */
        input[type="text"], input[type="tel"], input[type="email"],
        input.client-name, input.nap_assign, input.act_number,
        input.email, input.monthlyfee, input.date, input.End,
        input.meters, input.previous-provider,
        input.downgrade-from, input.downgrade-to {
            border: none;
            border-bottom: 1.5px solid #c0392b;
            outline: none;
            padding: 2px 4px;
            font-size: 0.82rem;
            background: transparent;
            color: #1a1a2e;
            width: 100%;
            box-sizing: border-box;
        }
        #address {
            margin: 0;
            font-size: 0.82rem;
            color: #1a1a2e;
        }
        /* ── Checkboxes ── */
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
        }
        .checkbox-item input[type="checkbox"] {
            accent-color: #c0392b;
            width: 14px;
            height: 14px;
        }
        /* ── Signature section ── */
        .signature-section {
            display: flex;
            gap: 24px;
            margin: 28px 0 16px;
        }
        .signature-box {
            flex: 1;
            text-align: center;
        }
        .signature-line {
            border-bottom: 2px solid #1a1a2e;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding-bottom: 6px;
        }
        .signature-line img {
            max-width: 200px;
            max-height: 70px;
            margin-bottom: 4px;
        }
        .sig-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #374151;
            letter-spacing: 0.5px;
            margin-top: 5px;
            text-transform: uppercase;
        }
        .btn-signature {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 7px 16px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 6px;
            box-shadow: 0 2px 8px rgba(37,99,235,0.3);
        }
        /* ── Terms ── */
        .terms {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px 20px;
            font-size: 0.78rem;
            line-height: 1.6;
            margin: 16px 0;
            color: #374151;
        }
        .terms ol { padding-left: 18px; margin: 8px 0 0; }
        .terms li { margin-bottom: 8px; text-align: justify; }
        /* ── Print button ── */
        .btn-print {
            display: block;
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            box-shadow: 0 3px 12px rgba(22,163,74,0.3);
            letter-spacing: 0.3px;
        }
        .btn-back {
            display: block;
            width: 100%;
            padding: 11px;
            background: #6b7280;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            letter-spacing: 0.3px;
        }
        .btn-back:hover { background: #4b5563; }
        /* ── Mobile ── */

        @media print {
            body { background: #fff; padding: 0; }
            .container { box-shadow: none; border-radius: 0; padding: 20px; }
            .btn-print, .btn-signature, .btn-back { display: none !important; }
            #signature-image { display: block !important; }
            .terms { border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="doc-header">
            <img src="logo-09022026.png" alt="UBILINK Logo" class="logo">
            <div class="doc-header-text">
                <p class="company">UBILINK</p>
                <p class="doc-title">Client Subscription Agreement</p>
                <p class="doc-subtitle">REGULAR FIBERLINK HOME PLAN</p>
            </div>
        </div>

        <table>
            <tr>
                <td style="width: 18%;"><strong>NAP Assignment:</td>
                    <td style="width: 32%;"> <input class="nap_assign" id="nap_assign" placeholder=""></strong></td> 
                <td style="width: auto"><strong>Account Number:</td> 
                    <td style="width: 32%;"><input class="act_number" id="act_number" placeholder=""></strong></td>
            </tr>
        </table>
            <table>
                <td style="width: 18%;" colspan="2"><strong>Client's Name:</td> <td>
                    <input size="80" maxlength="100" class="client-name" id="client-name" placeholder=""></strong></td>    
            </table>
            <table>
                <td style="width: 18%;"><strong>Complete Address: </td>
                    <td style="width: 32%;">
                        <p id="address"><?php echo htmlspecialchars($installation['address'] ?? ''); ?></p>
                    </td>
                <td style="width: 18%;" > <strong>Account: Additional Information</strong> </td>
                <td> <strong>FIBER Plans: Check (Chosen plan)</strong></td>
            </table>
            <table>
                <td style="width: 18%;"><strong>Email Address: </td>
                    <td style="width: 32%;"><input class="email" id="email" placeholder=""></strong></td>
                <td style="width: 18%;"> <div class="checkbox-item">
                                <input type="checkbox" id="new-client">
                                <label for="new-client">New Client</label>
                            </div></td>
                 <td><strong></strong></td>           
            </table>
            <table>
                <td style="width: 18%;"><strong>Log-in Password: </td>
                    <td style="width: 32%;"><strong>123456</strong></td>
                <td style="width: 18%;"> <div class="checkbox-item">
                                <input type="checkbox" id="migrate">
                                <label for="migrate">Migrate</label>
                            </div> </td>
                <td></td>            
           </table>
                <table>
                    <td style="width: 18%;"><strong>Mobile Number: </td>
                       <td style="width: 32%;"> <input type="tel" id="mobile" placeholder=""></strong></td>
                    <td style="width: 18%;"><div class="checkbox-item">
                                <input type="checkbox" id="reconnection">
                                <label for="reconnection">Re-Connection</label>
                            </div> </td>
                      <td style="width: 32%;">
                          <div class="checkbox-item">
                              <input type="checkbox" id="plan-selected" checked>
                              <label for="plan-selected" id="plan-name-label"><?php echo htmlspecialchars($plan_name); ?></label>
                          </div>
                      </td>      
                </table>
                <table>
                <td style="width: 18%;"><strong>TL /Team In-Charge: </td>
                    <td style="width: 32%;"><input type="text" id="team-incharge" placeholder=""></strong></td>
                <td style="width: auto;">  <div class="checkbox-item"><input type="checkbox" id="Downgrade"> <label for="Downgrade"> Downgrade</label> </div>
                            <strong>From:</strong> <input id="downgrade-from" class="downgrade-from"> <br>
                                <strong>To:</strong>
                                <input class="downgrade-to" id="downgrade-to" width="10px"> </td>
                </table>           
                <table> 
                    <td style="width: 18%;"><strong>Total Initial Amount Paid (1st month/installation 100%)</strong></td>
                    <td style="width: auto;">1st Monthly Fee <br>(Plan) 
                        <input   size="15.5" maxlength=""; type="other-details" class="monthlyfee" id="monthlyfee" ></td>
                    <td>From: 
                        <input size="11" maxlength="10" type="text" class="date" id="date"> 
                        <br>To: <input size="11" maxlength="10" class="End" id="End"> </td>
                    <td >FOC > 500 Meters <br><br>PHP 
                        <input size="5" maxlength="4"; type="other-details" class="meters" id="meters"></td>
                    <td > <div class="checkbox-item">
                        <input  type="checkbox" id="switch"> <label for="switch"> Switcher Promo</label></div>
                         <br>From <br><input size="25" maxlength="16";type="other-details" class="previous-provider" id="previous-provider"> 
                         <br>Previous Plan: <input  size="25" maxlength="4"type="other-details" type="Previous_Plan" name="Previous_Plan" id="Previous_Plan"></td>
                </table>
               <table>
                <td style="width: 18%;">
                    <strong>Add-Ons Devices:</strong>
                    <td style="width: 27.3%;">
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="ups">
                            <label for="ups">UPS </label>
                        </div>
                    </td>
                    <td style="width: 27.3%;">
                        <div class="checkbox-item">
                            <input type="checkbox" id="mesh">
                            <label for="mesh">TP Link 1 Mesh</label>
                        </div>
                    </td>
                    <td style="width: 27.3%;">
                        <div class="checkbox-item">
                            <input type="checkbox" id="deco">
                            <label for="deco">TP Link DECO</label>
    </div>
                </td>
                </table>
                <table>
                    <td style="width: 18%;">
                    <div  class="checkbox-item"><input type="checkbox" name="onetime" id="onetime"> <label for="onetime">One time payment</label></div>
                    <td style="width: 27.3%;">P800 </td>
                    <td style="width: 27.3%;">P1700 </td>
                    <td style="width: 27.3%;">P2000 </td>
            
                    
                
                </table>
          <table>
            <td>  
                <div  class="checkbox-item"><input type="checkbox" name="installment" id="installment"> 
                    <label for="installment">Installment</label>
                </div>
            </td>
                    <td style="width: 27.3%;">P200 For 4 months </td>
                    <td style="width: 27.3%;">P500 For 4 months </td>
                    <td style="width: 27.3%;">P500 For 5 months</td>
        </table>

        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">
                    <img id="signature-image" src="" alt="" style="max-width: 250px; max-height: 100px; display: <?php echo !empty($client_signature) ? 'block' : 'none'; ?>; margin-bottom: 5px; border: none; background: transparent;">
                    <button type="button" class="btn-signature" onclick="openSignaturePad()" style="display: <?php echo empty($client_signature) ? 'block' : 'none'; ?>;">✍️ Add Signature</button>
                </div>
                <p class="sig-label">CLIENT SIGNATURE</p>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    <p id="date-installed" style="border: none; width: 100%; margin: 0;">
                        <?php 
                        if ($installation && !empty($installation['installation_date']) && $installation['installation_date'] != '0000-00-00') {
                            echo date('F d, Y', strtotime($installation['installation_date']));
                        } else {
                            echo date('F d, Y');
                        }
                        ?>
                    </p>
                </div>
                <p class="sig-label">DATE INSTALLED</p>
            </div>
        </div>

        <div class="terms">
            <p style="margin-bottom: 10px;"><em>By my signature above, I acknowledge that I have completely read, fully understand and agree to the policies and procedures of UBILINK</em></p>
            
            <ol>
                <li>Free of charge ang installation ng UBILINK Fiber internet, <strong>subalit may charge na Php 500 para sa karangdagang Fiber cable na mairerequire</strong> site hindi sa 500 meters ang layon ng site mula sa location ng UBILINK Nap Box. Ito ay non-refundable at non-transferable.</li>
                
                <li>Kung sakaling magkaroon ng factory defect lamang ang valid reason upang mapalitan ng UBILINK ang device-free of charge. One Thousand pesos (Php 1,000) naman ang halaga na dapat bayaran ng Client sa ano mang physical damage ng device na ito ay hindi sinasadyang nangyari o kadalasan.</li>
                
                <li>Ang Subscription sa UBILINK ay may kailang na <strong><?php echo addslashes($installation['contract_duration'] ?? '12'); ?> month binding contract </strong>. Kung naisin ng Client na magpa-early termination ng subscription, babayaran ng Client ang remaining months (1 month, monthly service fee (MSF) bilang termination fee o charge. <strong>Ang mga recoverable equipments tulad ng Router ay mananatiling pagmamay-ari ng UBILINK, at kailangang ibalik ng Subscriber sa UBILINK.</strong></li>
                
                <li>Maaring piliin ng Client na hindi masakop sa <strong><?php echo addslashes($installation['contract_duration'] ?? '12'); ?> month binding contract</strong>, sa pamamagitan ng pagbabayad ng one time payment <strong>Buy Your Own Device (BYOD)</strong> na nagkakahalaga ng Php 3,500 para sa Fiber Router. Ang BYOD ay may kasamang 1 time replacement, para sa factory defect only) para sa mga kagamitan ng kalangian upang mainstalan ang Client ng kanyang internet. Sa gayon na ito ay magiging pag-aari na ng subscriber at walang kailangang ibalik sa UBILINK kung kanyang current balance.</li>
                
                <li>Ang mga Add-on items such as UPS, Wi-Fi Mesh ay maging fully property na ni Client once paid. Ito ay may wear & tear expectancy kung kayat hindi pananagutan ni UCC ang mga items na ito kung sakaling ang mga devices na ito ay masira na.</li>
                
                <li>Ang Client ay dapat maging responsable sa paggamit ng Internet Service na ito. Walang pananagutan ang UBILINK sa ano mang information, picture, video at iba pang data na ii-upload or i-da-download ng Client mula sa internet.</li>
                
                <li>Ang pagbabayad ay responsibilidad ng Client. Sa UBILINK Office, iminimong online payment system, o sa authorized representative ng UBILINK sa lugar makikita ang monthly subscription fee o ano mang singil na dapat bayaran.</li>
            </ol>
        </div>

        <button id="download-btn" class="btn-print" onclick="saveAsImage()">📷 Save as Image</button>
        <button class="btn-back" onclick="closeTab()">← Back</button>
    </div>

    <script>
    function saveAsImage() {
        var btn = document.getElementById('download-btn');
        var backBtn = document.querySelector('.btn-back');
        var signatureBtn = document.querySelector('.btn-signature');
        var container = document.querySelector('.container');

        btn.textContent = '⏳ Saving...';
        btn.disabled = true;
        btn.style.visibility = 'hidden';
        btn.style.opacity = '0';
        if (backBtn) { backBtn.style.visibility = 'hidden'; backBtn.style.opacity = '0'; }
        if (signatureBtn) signatureBtn.style.display = 'none';

        // Replace each input/textarea with a styled span showing its value
        var replacements = [];
        container.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"], input:not([type="checkbox"]):not([type="submit"]), textarea').forEach(function(inp) {
            var span = document.createElement('span');
            span.textContent = inp.value || '';
            span.style.cssText = 'display:inline-block;width:100%;color:#1a1a2e;font-size:0.82rem;font-family:inherit;border-bottom:1.5px solid #c0392b;padding:2px 4px;box-sizing:border-box;min-height:18px;background:transparent;';
            inp.parentNode.insertBefore(span, inp);
            inp.style.display = 'none';
            replacements.push({ inp: inp, span: span });
        });

        var signatureImg = document.getElementById('signature-image');
        if (signatureImg && signatureImg.src && signatureImg.src !== window.location.href) {
            signatureImg.style.display = 'block';
        }

        // Wait two animation frames so the browser repaints before html2canvas captures
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                html2canvas(container, {
                    scale: 2,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    logging: false,
                    ignoreElements: function(el) {
                        return el === btn || (signatureBtn && el === signatureBtn) || (backBtn && el === backBtn);
                    }
                }).then(function(canvas) {
                    // Restore inputs
                    replacements.forEach(function(r) {
                        r.inp.style.display = '';
                        r.span.parentNode.removeChild(r.span);
                    });

                    var link = document.createElement('a');
                    var clientName = document.getElementById('client-name') ? document.getElementById('client-name').value.trim() : '';
                    var filename = clientName ? 'subscription_' + clientName.replace(/\s+/g, '_') + '.jpg' : 'subscription_agreement.jpg';
                    link.download = filename;
                    link.href = canvas.toDataURL('image/jpeg', 0.95);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    btn.style.visibility = '';
                    btn.style.opacity = '';
                    btn.textContent = '📷 Save as Image';
                    btn.disabled = false;
                    if (backBtn) { backBtn.style.visibility = ''; backBtn.style.opacity = ''; }
                    if (signatureBtn) signatureBtn.style.display = '';
                }).catch(function(err) {
                    replacements.forEach(function(r) {
                        r.inp.style.display = '';
                        r.span.parentNode.removeChild(r.span);
                    });
                    console.error('Save as image failed:', err);
                    alert('Failed to save image: ' + err.message);
                    btn.style.visibility = '';
                    btn.style.opacity = '';
                    btn.textContent = '📷 Save as Image';
                    btn.disabled = false;
                    if (backBtn) { backBtn.style.visibility = ''; backBtn.style.opacity = ''; }
                    if (signatureBtn) signatureBtn.style.display = '';
                });
            });
        });
    }

    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'signature') {
            var signatureImg = document.getElementById('signature-image');
            var signatureBtn = document.querySelector('.btn-signature');
            if (signatureImg) {
                signatureImg.src = event.data.signature;
                signatureImg.style.display = 'block';
                if (signatureBtn) signatureBtn.style.display = 'none';
                var installationId = <?php echo $installation_id; ?>;
                if (installationId > 0) {
                    var formData = new FormData();
                    formData.append('installation_id', installationId);
                    formData.append('signature_data', event.data.signature);
                    fetch('save_signature.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) alert('Signature saved successfully!');
                            else alert('Error saving signature: ' + data.message);
                        })
                        .catch(() => alert('An error occurred while saving the signature.'));
                }
            }
        }
    });

    function openSignaturePad() {
        var signatureWindow = window.open('Signaturepad.html', 'SignaturePad', 'width=900,height=700');
        if (!signatureWindow) alert('Please allow popups for this site to use the signature pad');
    }

    function closeTab() {
        window.close();
        // Fallback if window.close() is blocked (e.g. tab wasn't opened by script)
        setTimeout(function() {
            if (!window.closed) history.back();
        }, 300);
    }

    window.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($client_signature)): ?>
        var signatureImg = document.getElementById('signature-image');
        if (signatureImg) {
            signatureImg.src = '<?php echo $client_signature; ?>';
            signatureImg.style.display = 'block';
        }
        <?php endif; ?>
    });
    </script>
</body>
</html>