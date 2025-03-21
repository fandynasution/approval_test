<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>IFCA - BTID</title>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo e(url('public/images/KuraKuraBali-iconew.ico')); ?>">
    
    <style>
        body {
            font-family: Arial;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>

</head>

<body width="100%" style="margin: 0; padding: 0 !important; mso-line-height-rule: exactly; background-color: #ffffff;">
	<div style="width: 100%; background-color: #ffffff; text-align: center;">
        <table width="80%" border="0" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="margin-left: auto;margin-right: auto;" >
            <tr>
               <td style="padding: 40px 0;">
                    <table style="width:100%;max-width:620px;margin:0 auto;">
                        <tbody>
                            <tr>
                                <td style="text-align: center; padding-bottom:25px">
                                    <img width = "120" src="<?php echo e(url('public/images/KURAKURABALI_LOGO.jpg')); ?>" alt="logo">
                                    <p style="font-size: 16px; color: #026735; padding-top: 0px;">PT. BALI TURTLE ISLAND DEVELOPMENT</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <table style="width:100%;max-width:620px;margin:0 auto;background-color:#e0e0e0;">
                        <tbody>
                            <tr>
                                <td style="text-align:center;padding: 50px 30px;">
                                    <img style="width:88px; margin-bottom:24px;" src="<?php echo e(url('public/images/double_approve.png')); ?>" alt="Verified">
                                    <p>Do you want to <?php echo e($valuebt); ?> this request ?</p>
                                    <form id="frmEditor" class="form-horizontal" method="POST" action="<?php echo e(url('/api/cmprogress/getaccess')); ?>" enctype="multipart/form-data">
                                    <?php echo csrf_field(); ?>
                                    <input type="text" id="status" name="status" value="<?php echo $status?>" hidden>
                                    <input type="text" id="encrypt" name="encrypt" value="<?php echo $encrypt?>" hidden>
                                    <?php if ($status != 'A'): ?>
                                        <?php if ($status == 'R'): ?>
                                            <p>Please provide the reasons for requesting this revision</p>
                                        <?php elseif ($status == 'C'): ?>
                                            <p>Please provide the reasons for requesting the cancellation of this revision</p>
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
                                        </div>
                                    <?php endif; ?>
                                    <input type="submit" class="btn" style="background-color:<?php echo $bgcolor?>;color:#ffffff;display:inline-block;font-size:13px;font-weight:600;line-height:44px;text-align:center;text-decoration:none;text-transform: uppercase; padding: 0px 40px;margin: 10px" value=<?php echo $valuebt?>>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <table style="width:100%;max-width:620px;margin:0 auto;">
                        <tbody>
                            <tr>
                                <td style="text-align: center; padding:25px 20px 0;">
                                    <p style="font-size: 13px;">Copyright © 2023 IFCA Software. All rights reserved.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
               </td>
            </tr>
        </table>
    </div>
</body>
</html><?php /**PATH /var/www/html/approval_live/resources/views/email/cmprogress/passcheckwithremark.blade.php ENDPATH**/ ?>