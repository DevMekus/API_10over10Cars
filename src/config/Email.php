<?php
class Email
{


    private function Mailer(array $data)
    {
        /**The Template of the EMail */
        $site_name = 'plustock Ltd';

        $html = "<html lang=\"en\">
        
        <head>
            <meta charset=\"UTF-8\">
            <meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css\" >
            <script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js\"></script>
            <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css\" />
            <script src=\"https://use.fontawesome.com/4138dd15b3.js\"></script>
            <title>" . $site_name . "</title>        
        </head>
        
        <body style=\"background-color: #f0f5f5; padding:10px\">
            <div style=\"width:90%; margin:auto; border:1px solid #d1e0e0; background-color: #ffffff;\">
            <div style=\" background-color:#E7ECEF; width:100%;\">
                <img class=\"img-responsive logo\" src=\"http://localhost:3000/logo-black-edit.jpg\" width=\"200\" style=\"margin-left:10px\"  alt=\"company_logo\">
            </div> <br>
                <div style=\"padding:20px\">
                    
                    <h3>" . $data['subject'] . "</h3>
                    <h3>Hello, " . $data['fullname'] . "</h3>
                   
                     " . $data['message'] . "
    
                    <p>                    
                       If you have any questions or concerns in the meantime, please do not hesitate to reach out to our customer support team at support@10over10cars.com.
                    </p>
        
                    <p>
                        Thank You,<br>
                        " . $site_name . " Team.
                    </p>
                </div>
        
            </div>
        
        </body>
        <script src=\"https://code.jquery.com/jquery-3.6.0.min.js\"></script>
        <!-- Latest compiled and minified JavaScript -->
        <script src=\"https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js\"></script>
        <script src=\"https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js\"></script>
        <script src=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/js/all.min.js\"></script>
        
        </html>";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers  .= "From: 10over10 Cars <support@10over10cars.com>\n";

        return mail($data['email'], $data['subject'], $html, $headers);
    }

    public function NewAccount(array $data)
    {
        $subject = "Welcome to 10over10 Cars! Your Account is Ready";
        $message = '<p>
        Thank you for registering with 10over10 Cars – we are excited to have you on board! Your new account is now ready, and you can start using our platform to access comprehensive vehicle reports and explore cars for sale.
        </p>';
        $message .= "<br/>";
        $message .= " 
        <h3>Here's what you can do next:</h3><br/>
        <ul>
        <li>Search for Vehicle Reports: Simply enter a Vehicle Identification Number (VIN) to get detailed information about a car’s history, including mileage, ownership, and any theft records.</li>
        <li>Browse Cars for Sale: Discover our featured vehicles listed by our admin and find the perfect car for you.</li>
        <li>Manage Your Account: Log in to your dashboard to view your search history, save car listings, and manage your account settings.</li>
        </ul><br/>
        <h3>Ready to get started?</h3><br/>
        <p>Log in to Your Account to begin exploring the platform.</p>
        ";

        $this->Mailer([
            'email' => $data['email'],
            'subject' => $subject,
            'fullname' => $data['fullname'],
            'message' => $message
        ]);
    }

    public function ResetAccount(array $data)
    {
        $subject = "Reset Account";
        $resetToken = $data['resetToken'];
        $message = '<p>So you wish to reset your account?<br/>
        Click on the reset link below to get started.
        </p>';
        $message .= "http://localhost:3000/auth/confirm?token=$resetToken";

        $this->Mailer([
            'email' => $data['email'],
            'subject' => $subject,
            'fullname' => $data['fullname'],
            'message' => $message
        ]);
    }

    public function Verification(array $data)
    {

        $message = '';

        $this->Mailer([
            'email' => $data['email'],
            'subject' => $data['subject'],
            'fullname' => $data['fullname'],
            'message' => $message
        ]);
    }
}
