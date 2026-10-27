<?php
// Add a custom dashboard widget
function custom_dashboard_widget() {
    wp_add_dashboard_widget(
        'custom_ad_widget',          // Widget ID
        'Modernlisim.com Services',          // Widget title
        'display_custom_ad_widget'   // Display function
    );
}
add_action('wp_dashboard_setup', 'custom_dashboard_widget');

// Display the custom dashboard widget content
function display_custom_ad_widget() {
    // Fetch a random image from Unsplash
    $unsplash_url = 'https://www.modernlisim.com/wp-content/uploads/2023/11/Modernlisim-Empowering-Your-Success.jpg'; // Change the dimensions as needed

    // Display the slider container
    ?>
    <style>
        #custom_ad_widget img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        
        h3.titleholderr {
    border: 1px solid #757575;
    border-radius: 16px;
    padding: 2px 10px 2px 10px !important;
    display: inline-block;
    box-sizing: border-box;
}
        
    </style>

    <div id="promotionSlider" style="max-width: 500px; margin: 20px auto; overflow: hidden;">
        <?php
        // Array of promotions
        $promotions = array(
            array(
                'title' => 'Web Development',
                'description' => 'Our expert team provides top-notch web development services.',
            ),
            array(
                'title' => 'UI/UX Design',
                'description' => 'Create visually appealing and user-friendly interfaces with our design experts.',
            ),
            array(
                'title' => 'Digital Marketing',
                'description' => 'Boost your online presence and reach your target audience with our digital marketing solutions.',
            ),
            array(
                'title' => 'SEO',
                'description' => 'Improve your website\'s search engine ranking and visibility with our SEO services.',
            ),
            array(
                'title' => 'Website Maintenance',
                'description' => 'Ensure your website is always up-to-date and running smoothly with our maintenance services.',
            ),
        );

        // Display each promotion as a slide
        foreach ($promotions as $promotion) {
            ?>
            <div class="promotionSlide" style="display: none;">
                <h3 class="titleholderr"><strong><?php echo esc_html($promotion['title']); ?></strong></h3>
                <p><?php echo esc_html($promotion['description']); ?></p>
                <a href="https://www.modernlisim.com" class="button button-primary">Need Help? Contact Us</a>
            </div>
            <?php
        }
        ?>
    </div>

    <div style="margin-top: 20px;">
        <img id="unsplashImage" src="<?php echo esc_url($unsplash_url); ?>" alt="Advertising Image">
    </div>

    <script>
        // JavaScript code for the slider
        document.addEventListener('DOMContentLoaded', function () {
            var slides = document.querySelectorAll('.promotionSlide');
            var currentIndex = 0;

            function showSlide(index) {
                slides.forEach(function (slide, i) {
                    slide.style.display = i === index ? 'block' : 'none';
                });
            }

            function nextSlide() {
                currentIndex = (currentIndex + 1) % slides.length;
                showSlide(currentIndex);
            }

            // Initial display
            showSlide(currentIndex);

            // Set interval to switch slides every 3 seconds
            setInterval(nextSlide, 3000);
        });
    </script>
    <?php
}

// No need for the save_custom_advertising_code function in this version
