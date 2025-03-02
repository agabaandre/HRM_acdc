<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>Happy Birthday!</title>
    <style type="text/css">
      body {
        margin: 0; 
        padding: 0; 
        background-color: #f4f4f4; 
        font-family: Arial, sans-serif;
      }
      .container {
        width: 100%; 
        max-width: 600px; 
        margin: 0 auto; 
        background: #ffffff; 
        padding: 20px; 
        position: relative;
        z-index: 1;
      }
      .header {
        text-align: center; 
        padding: 5px 0;
      }
      .header img {
        max-width: 150px;
      }
      .content {
        padding: 10px;
      }
      .footer {
        text-align: center; 
        font-size: 12px; 
        color: #888888; 
        padding: 10px 0;
      }
      h1 {
        color: #2A2A2A;
      }
      p {
        line-height: 1.5;
      }
  
      
      /* Animation container for flying hearts and glittering flowers */
      .animation-container {
        position: fixed;
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%;
        pointer-events: none; 
        overflow: hidden;
        z-index: 0;
      }
      .heart, .flower {
        position: absolute;
        font-size: 24px;
        opacity: 0;
      }
      .heart {
        color: #FF69B4;
        animation: fly 5s linear infinite;
      }
      .flower {
        color: #FFD700;
        animation: flyFlower 6s linear infinite;
      }
      @keyframes fly {
        0% {
          transform: translateY(100vh) scale(0.5);
          opacity: 0;
        }
        25% {
          opacity: 1;
        }
        100% {
          transform: translateY(-10vh) scale(1.2);
          opacity: 0;
        }
      }
      @keyframes flyFlower {
        0% {
          transform: translateY(100vh) scale(0.5);
          opacity: 0;
        }
        25% {
          opacity: 1;
        }
        100% {
          transform: translateY(-10vh) scale(1.2);
          opacity: 0;
        }
      }
    </style>
  </head>
  <body>
    <div class="animation-container">
      <!-- Flying Hearts -->
      <div class="heart" style="left: 10%; animation-delay: 0s;">&#10084;</div>
      <div class="heart" style="left: 30%; animation-delay: 1s;">&#10084;</div>
      <div class="heart" style="left: 50%; animation-delay: 2s;">&#10084;</div>
      <div class="heart" style="left: 70%; animation-delay: 3s;">&#10084;</div>
      <div class="heart" style="left: 90%; animation-delay: 4s;">&#10084;</div>
      <!-- Glittering Flowers -->
      <div class="flower" style="left: 20%; animation-delay: 0.5s;">&#127802;</div>
      <div class="flower" style="left: 40%; animation-delay: 1.5s;">&#127802;</div>
      <div class="flower" style="left: 60%; animation-delay: 2.5s;">&#127802;</div>
      <div class="flower" style="left: 80%; animation-delay: 3.5s;">&#127802;</div>
    </div>
    
    <div class="container">
      <!-- Header with Africa CDC logo -->
      <div class="header">
        <img src="https://khub.africacdc.org/storage/uploads/config/fcb24779b37db15ee15fd4a32eaab0ac.png" alt="Africa CDC">
      </div>
      <!-- Email Content -->
      <div class="content">
        <h1>Happy Birthday!</h1>
        <p>Dear <?php echo $name; ?>,</p>
        <p>
          On behalf of everyone at Africa CDC, we wish you a very happy birthday! Today is a special day to celebrate you and all the wonderful contributions you make to our community.
        </p>
        <p>
          May your day be filled with joy, laughter, and delightful surprises. Enjoy the festivities and know that you are truly appreciated!
        </p>
        <p>
          Enjoy your special day!
        </p>
        <p>
          Best wishes,<br>
          The Africa CDC Family
        </p>
        <p>
         
        </p>
      </div>
      <!-- Footer -->
      <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Africa CDC. All Rights Reserved.</p>
      </div>
    </div>
  </body>
</html>
