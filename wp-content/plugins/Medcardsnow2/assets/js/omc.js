(function($){
  if (typeof OMC === 'undefined') {
    window.OMC = { ajax_url: '/wp-admin/admin-ajax.php', nonce: '' };
  }

  const ajaxURL    = OMC.ajax_url,
        nonce      = OMC.nonce,
        lostPassURL= OMC.lost_password_url || '',
        currentUser= OMC.current_user || {},
        settings   = OMC.settings || {};

  const mount = document.getElementById('omc-app');
  if (!mount) return;

  // ---------------- APP STATE -----------------
  const app = {
    user:  { email:'', name:'', phone:'', address:'' },
    state: '',
    plan:  null,
    addHardCopy: false,
    addIdCard:   false,
    duration:    null,
    coupon: { applied:false, code:'', percent:100 },
    signatureData: ''
  };

  let sigPadInitialized = false;

  const fmt = n => `$${Number(n).toFixed(2)}`;
  const qs  = s => document.querySelector(s);
  const qsa = s => Array.from(document.querySelectorAll(s));

  const DISCOUNT = { y2: 0.09, y3: 0.15 };

  const STATE_BASE_1Y = {
    "Arizona":149.99,"Arkansas":179.99,"California":39.99,"Connecticut":149.99,
    "Delaware":149.99,"Georgia":149.99,"Illinois":179.99,"Iowa":199.99,
    "Louisiana":179.99,"Maine":69.99,"Maryland":139.99,"Massachusetts":139.99,
    "Michigan":129.99,"Minnesota":159.99,"Missouri":129.99,"Montana":139.99,
    "Nevada":79.99,"New Jersey":149.99,"New York":129.99,"Ohio":129.99,
    "Oklahoma":129.99,"Pennsylvania":149.99,"Texas":149.99,"Virginia":169.99,
    "Washington":179.99,"West Virginia":179.99
  };

  // Generate review actions HTML dynamically
  function getReviewActionsHTML() {
    if (app.state === 'California') {
      return `
        <button class="btn btn-outline-secondary btn-sm" data-goto="5" style="border: 2px solid #6f7379; border-radius: 6px;">
          <i class="bi bi-pencil-square me-1"></i>Change Plan
        </button>
        <button class="btn btn-outline-secondary btn-sm" data-goto="8" style="border: 2px solid #6f7379; border-radius: 6px;">
          <i class="bi bi-pencil-square me-1"></i>Change Duration
        </button>
        <button class="btn btn-outline-secondary btn-sm" data-goto="4" style="border: 2px solid #6f7379; border-radius: 6px;">
          <i class="bi bi-pencil-square me-1"></i>Medical Info
        </button>
      `;
    } else {
      return `
        <button class="btn btn-outline-secondary btn-sm" data-goto="8" style="border: 2px solid #6f7379; border-radius: 6px;">
          <i class="bi bi-pencil-square me-1"></i>Change Duration
        </button>
        <button class="btn btn-outline-secondary btn-sm" data-goto="4" style="border: 2px solid #6f7379; border-radius: 6px;">
          <i class="bi bi-pencil-square me-1"></i>Medical Info
        </button>
      `;
    }
  }

  mount.innerHTML = `
  <header class="py-3 border-bottom bg-white position-sticky top-0" style="z-index:1030; border-color: #6f7379 !important;">
    <div class="container">
      <div class="row align-items-center">
        <!-- Logo/Brand - Left side -->
        <div class="col-6 col-md-4">
          <span class="brand-pill fw-semibold d-inline-flex align-items-center" style="background: #1AA3A9; color: white; padding: 0.5rem 1.5rem; border-radius: 50px; font-size: 0.9rem; white-space: nowrap;">
            <i class="bi bi-heart-pulse me-2"></i>Med Cards Now
          </span>
        </div>
        
        <!-- User Badge - Center on mobile, right on desktop -->
        <div class="col-12 col-md-4 order-3 order-md-2 mt-2 mt-md-0">
          <div class="d-flex justify-content-center">
            <span id="userNameBadge" class="text-secondary small d-none px-3 py-1 rounded" style="background: #E8F6F7; color: #1AA3A9; border: 1px solid #D5EFF0;">
              <i class="bi bi-person-circle me-1"></i><span id="userEmailDisplay"></span>
            </span>
          </div>
        </div>
        
        <!-- Actions - Right side -->
        <div class="col-6 col-md-4 order-2 order-md-3">
          <div class="d-flex align-items-center justify-content-end gap-2">
            <!-- Login/Logout Button -->
            <button class="btn btn-sm d-flex align-items-center" id="loginTopBtn" style="border: 2px solid #1AA3A9; color: #1AA3A9; background: transparent; border-radius: 6px; padding: 0.4rem 0.8rem; font-size: 0.875rem;">
              <i class="bi bi-box-arrow-in-right me-1 d-none d-sm-inline"></i>
              <span>Login</span>
            </button>
            
            <!-- Theme Toggle -->
            <button class="btn btn-sm d-flex align-items-center justify-content-center" id="themeToggle" aria-pressed="false" title="Toggle light/dark" style="border: 2px solid #6f7379; color: #334155; background: transparent; border-radius: 6px; padding: 0.4rem; width: 2.5rem; height: 2.5rem;">
              <i class="bi bi-moon-stars"></i>
            </button>
          </div>
        </div>
      </div>
      
      <!-- Mobile User Info (Hidden by default, shows when logged in on mobile) -->
      <div class="row mt-2 d-md-none">
        <div class="col-12">
          <div id="mobileUserInfo" class="d-none text-center">
            <span class="text-secondary small" style="color: #1AA3A9 !important;">
              <i class="bi bi-person-check me-1"></i>Signed in as <span id="mobileUserEmail"></span>
            </span>
          </div>
        </div>
      </div>
    </div>
  </header>

  
<main class="container-fluid my-4 my-md-5 px-3 px-md-5">
    <!-- STEP 1: REGISTER -->
  <section id="step-1" class="step">
    <div class="card card-soft p-0 overflow-hidden">
        <div class="row g-0">
            <!-- Left Side - Full Size Image -->
            <div class="col-lg-6 d-none d-lg-block">
                <div class="h-100 position-relative">
                  <img src="${OMC.images?.signup_hero || OMC.images?.login_hero || 'assets/Hero.jpg'}"
                         class="w-100 h-100 object-fit-cover" 
                         alt="Medical Card Journey" 
                         style="object-fit: cover;">
                    <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                       
                    </div>
                </div>
            </div>
          
          <!-- Right Side - Registration Form -->
          <div class="col-lg-6">
            <div class="p-4 p-md-5">
              <h2 class="mb-1" style="color: #198754; font-size: 2.9rem; font-weight: 700;">Create your account</h2>
              <p class="text-secondary mb-4">Sign up to start your medical card application.</p>
              
              <form id="formRegister" class="needs-validation" novalidate>
                <div class="mb-3">
                  <label class="form-label required">Name</label>
                  <input type="text" class="form-control" name="firstName" placeholder="Enter your name" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                </div>

  <div class="mb-3">
                  <label class="form-label required">Phone Number</label>
          <input type="text" class="form-control" name="phone" maxlength="14" required oninput="formatPhone(this);" style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                </div>

                <div class="mb-3">
                  <label class="form-label required">Email</label>
                  <div class="input-group has-validation">
                    <input type="email" class="form-control" name="regEmail" placeholder="your@email.com" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                    <div class="invalid-feedback">Please enter a valid email address</div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label required">Password</label>
                  <div class="input-group has-validation">
                    <input type="password" class="form-control" name="regPassword" placeholder="Create password" minlength="6" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                    <button class="btn btn-outline-secondary" type="button" id="toggleRegPassword" style="border: 2px solid #6f7379; margin-left: -2px;">
                      <i class="bi bi-eye"></i>
                    </button>
                    <div class="invalid-feedback">Password must be at least 6 characters</div>
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label required">Confirm Password</label>
                  <div class="input-group has-validation">
                    <input type="password" class="form-control" name="confirmPassword" placeholder="Confirm password" minlength="6" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" style="border: 2px solid #6f7379; margin-left: -2px;">
                      <i class="bi bi-eye"></i>
                    </button>
                    <div class="invalid-feedback">Passwords must match</div>
                  </div>
                </div>

                <div class="mb-4">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="terms" id="terms" required style="border: 2px solid #6f7379;">
                    <label class="form-check-label" for="terms">
                      I agree with <a href="#" class="link-primary" style="color: #1AA3A9;">Terms</a> and <a href="#" class="link-primary" style="color: #1AA3A9;">Privacy Policy</a>
                    </label>
                    <div class="invalid-feedback">You must agree to the terms and conditions</div>
                  </div>
                </div>

                <div class="d-grid">
                  <button class="btn btn-lg" type="submit" style="background: #1AA3A9; color: white; border: none; border-radius: 8px; padding: 12px; font-weight: 600;">Next</button>
                </div>

                <div class="text-center mt-3">
                  <p class="mb-0">Already have an account? 
                    <a href="#" class="fw-semibold" data-goto="2" style="color: #1AA3A9;">Login</a>
                  </p>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- STEP 2: LOGIN -->
    <section id="step-2" class="step d-none">
      <div class="card card-soft p-0 overflow-hidden">
        <div class="row g-0">
          <!-- Left Side - Full Size Image -->
          <div class="col-lg-6 d-none d-lg-block">
            <div class="h-100 position-relative">
              <img src="${OMC.images?.login_hero || 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80'}" 
                   class="w-100 h-100 object-fit-cover" 
                   alt="Welcome Back" 
                   style="object-fit: cover;">
              <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                
              </div>
            </div>
          </div>
          
          <!-- Right Side - Login Form -->
          <div class="col-lg-6">
            <div class="p-4 p-md-5">
              <div class="text-center mb-4">
                <h2 class="mb-1" style="color: #1AA3A9; font-size: 2.2rem; font-weight: 700;">Welcome Back</h2>
                <p class="text-secondary">Please login to continue your application</p>
              </div>
              
              <form id="formLogin" class="needs-validation" novalidate>
                <div class="mb-3">
                  <label class="form-label required">Email</label>
                  <input type="email" name="email" class="form-control" placeholder="your@email.com" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                  <div class="invalid-feedback">Enter a valid email address</div>
                </div>
                
                <div class="mb-3">
                  <label class="form-label required">Password</label>
                  <div class="input-group">
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" minlength="6" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                    <button class="btn btn-outline-secondary" type="button" id="togglePass" style="border: 2px solid #6f7379; margin-left: -2px;">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                  <div class="invalid-feedback">Password is required</div>
                </div>
                
                <div class="form-check mb-4">
                  <input class="form-check-input" type="checkbox" id="rememberMe" style="border: 2px solid #6f7379;">
                  <label class="form-check-label" for="rememberMe">Remember me</label>
                </div>
                
                <div class="d-grid gap-2">
                  <button class="btn btn-lg" type="submit" style="background: #1AA3A9; color: white; border: none; border-radius: 8px; padding: 12px; font-weight: 600;">Login</button>
                  <button class="btn btn-outline-secondary" type="button" data-goto="1" style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;background: #198754; color: #fff;">Back to Sign Up</button>
                </div>
                
                <div class="text-center mt-4">
                  <a href="#" class="small" id="forgotPassLink" style="color: #1AA3A9;">Forgot your password?</a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- STEP 3: CONFIRM CONTACT -->
    <section id="step-3" class="step d-none">
      <div class="card card-soft p-4 p-md-5 text-center">
        <h3 class="mb-3" style="color: #198754;">Confirm Contact Details</h3>
        <p class="lead">
          The primary form of communication will be email. Please make sure
          <strong id="confirmEmail">example@mail.com</strong> is your correct email address.
        </p>
        <p class="fw-semibold">Do you confirm this email?</p>
        <div class="d-flex justify-content-center gap-3">
          <button class="btn btn-outline-secondary" data-goto="2" style=" background: #198754; color: #fff ;border: 2px solid #6f7379; border-radius: 8px; padding: 10px 20px;">Back</button>
          <button class="btn btn-success" id="confirmYes" style="background: #1AA3A9; border: none; border-radius: 8px; padding: 10px 20px;">Yes, Continue</button>
        </div>
      </div>
    </section>

    <!-- STEP 4: MEDICAL INFO -->
   <!-- STEP 4: MEDICAL INFO -->
<section id="step-4" class="step d-none">
  <div class="card card-soft p-4 p-md-5">
    <div class="d-flex align-items-center justify-content-between mb-4">
      <h4 class="mb-0" style="color: #198754;">Medical Information</h4>
      <div class="text-secondary" id="patientHeader"></div>
    </div>
    
    <form id="formMedical" class="needs-validation" novalidate>
      <div class="row g-3">
        <!-- Personal Information -->
        <div class="col-12">
          <h5 class="border-bottom pb-2 mb-3" style="color: #1AA3A9; border-color: #6f7379 !important;">Personal Information</h5>
        </div>
        
        <div class="col-md-4">
          <label class="form-label required">First Name</label>
          <input type="text" class="form-control" name="firstName" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
        </div>
        
        <div class="col-md-4">
          <label class="form-label">Middle Name</label>
          <input type="text" class="form-control" name="middleName" style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
        </div>
        
        <div class="col-md-4">
          <label class="form-label required">Last Name</label>
          <input type="text" class="form-control" name="lastName" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
        </div>
        
        <div class="col-md-6">
          <label class="form-label required">Phone Number</label>
          <input type="text" class="form-control" name="phone" maxlength="14" required oninput="formatPhone(this);" style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
        </div>
        
        <div class="col-md-6">
          <label class="form-label required">Email</label>
          <input type="email" class="form-control" name="email" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
        </div>

        <!-- Medical Information -->
        <div class="col-12 mt-4">
          <h5 class="border-bottom pb-2 mb-3" style="color: #1AA3A9; border-color: #6f7379 !important;">Medical Details</h5>
        </div>

        <div class="col-md-4">
          <label class="form-label required">Date of Birth</label>
          <div class="row g-2">
            <div class="col-4">
              <select class="form-select" name="dobMonth" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                <option value="" selected disabled>Month</option>
                <option>01</option><option>02</option><option>03</option><option>04</option>
                <option>05</option><option>06</option><option>07</option><option>08</option>
                <option>09</option><option>10</option><option>11</option><option>12</option>
              </select>
            </div>
            <div class="col-4">
              <select class="form-select" name="dobDay" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                <option value="" selected disabled>Day</option>
              </select>
            </div>
            <div class="col-4">
              <select class="form-select" name="dobYear" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
                <option value="" selected disabled>Year</option>
              </select>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label required">Gender</label>
          <select class="form-select" name="gender" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
            <option value="" disabled selected>Choose...</option>
            <option>Male</option>
            <option>Female</option>
            <option>Intersex</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label required">State you are applying for</label>
          <select class="form-select" name="state" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
            <option value="" selected disabled>Choose...</option>
            <option>Arizona</option><option>Arkansas</option><option>California</option>
            <option>Connecticut</option><option>Delaware</option><option>Georgia</option>
            <option>Illinois</option><option>Iowa</option><option>Louisiana</option>
            <option>Maine</option><option>Maryland</option><option>Massachusetts</option>
            <option>Michigan</option><option>Minnesota</option><option>Missouri</option>
            <option>Montana</option><option>Nevada</option><option>New Jersey</option>
            <option>New York</option><option>Ohio</option><option>Oklahoma</option>
            <option>Pennsylvania</option><option>Texas</option><option>Virginia</option>
            <option>Washington</option><option>West Virginia</option>
          </select>
        </div>

        <div class="col-md-12">
          <label class="form-label">What are your allergies? (if any)</label>
          <input class="form-control" name="allergies" style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;"/>
          <div class="form-text">List any known allergies or leave blank if none.</div>
        </div>

        <div class="col-12">
          <label class="form-label">Medical Conditions</label>
          <div class="row row-cols-2 row-cols-md-4 g-2">
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="AIDS" id="cnd1" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd1">AIDS</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Anorexia" id="cnd2" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd2">Anorexia</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Arthritis" id="cnd3" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd3">Arthritis</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Cachexia" id="cnd4" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd4">Cachexia</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Cancer" id="cnd5" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd5">Cancer</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Fibromyalgia" id="cnd6" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd6">Fibromyalgia</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Glaucoma" id="cnd7" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd7">Glaucoma</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Migraine" id="cnd8" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd8">Migraine</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Seizures" id="cnd9" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd9">Seizures</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Severe Nausea" id="cnd10" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd10">Severe nausea</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Persistent muscle spasms" id="cnd11" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd11">Persistent muscle spasms</label></div></div>
            <div class="col"><div class="form-check"><input class="form-check-input" type="checkbox" value="Other" id="cnd12" style="border: 2px solid #6f7379;"><label class="form-check-label" for="cnd12">Other</label></div></div>
          </div>

          <div class="mt-2 d-none" id="otherConditionWrap">
            <label class="form-label">Please describe your other medical condition</label>
            <textarea class="form-control" name="otherCondition" rows="3" placeholder="Describe your condition" style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;"></textarea>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label required">Upload Your ID (image or PDF)</label>
          <div class="input-group">
            <input type="file" class="form-control" id="idUpload" accept="image/*,application/pdf,.pdf" required style="border: 2px solid #6f7379; border-radius: 8px; padding: 12px;">
            <button class="btn btn-outline-secondary" type="button" id="takePicture" style="background:#198754 ; color: #fff;border: 2px solid #6f7379; margin-left: -2px; border-radius: 8px;">Choose or Take a Picture</button>
            <div class="invalid-feedback">Please upload an ID</div>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Patient's Signature</label>
          <div class="text-danger small mb-1">
            Note: Filling out this questionnaire does not guarantee a medical recommendation.
          </div>
          <canvas id="sigCanvas" class="signature-pad w-100" style="border: 2px solid #6f7379; border-radius: 8px;"></canvas>
          <div class="mt-2 d-flex gap-2">
            <button class="btn btn-sm btn-outline-danger" type="button" id="clearSig" style="border: 2px solid #6f7379;background: #198754; color: #fff; border-radius: 6px;">Clear</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="saveSig" style="border: 2px solid #6f7379; border-radius: 6px;background: #198754; color: #fff;">Save</button>
            <span class="text-success small d-none" id="sigSavedMsg">Signature saved</span>
          </div>
        </div>

        <div class="col-12">
          
          <div class="form-check mb-3">
            <input class="form-check-input" type="radio" name="telemedicine" id="tmYes" value="yes" required style="border: 2px solid #6f7379;">
            <label class="form-check-label fw-semibold" for="tmYes" style="color: #1AA3A9;">
              Yes, I consent to telemedicine
            </label>
          </div>
          <div class="alert alert-light border" style="background: #E8F6F7; border-color: #D5EFF0 !important; color: #334155;">
            <small><strong>Disclaimer:</strong> By using this site I consent to telemedicine: Telehealth means the mode of delivering health care services and public health via information and communication technologies to facilitate the diagnosis, consultation, treatment, education, care management, and self-management of a patient's health care.</small>
          </div>
        </div>
      </div>
      
      <!-- Security Notice -->
      <div class="alert alert-info mt-4" style="background-color: #E8F6F7; border-color: #1AA3A9; color: #334155;">
        <div class="d-flex align-items-center">
          <i class="bi bi-shield-check me-2" style="color: #1AA3A9;"></i>
          <span>Your information is 100% secure & encrypted</span>
        </div>
      </div>
      
      <div class="d-flex justify-content-between mt-4">
        <button class="btn " data-goto="3" type="button" style=" background: #198754; color: #fff; border: 2px solid #6f7379; border-radius: 8px; padding: 12px 24px;">Back</button>
        <button class="btn" type="submit" style="background: #1AA3A9; color: white; border: none; border-radius: 8px; padding: 12px 24px; font-weight: 600;">Next</button>
      </div>
    </form>
  </div>
</section>
    <!-- STEP 5: CALIFORNIA PLANS -->
    <section id="step-5" class="step d-none">
      <div class="card card-soft p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h4 class="mb-0" style="color: #1AA3A9;">Choose a California Plan</h4>
          <div><a class="link-secondary small me-3" href="#" id="logoutLink" style="color: #1AA3A9;">Logout</a></div>
        </div>
        <div class="row g-3" id="californiaPlansGrid"></div>
       
        <div class="mt-4" id="hardCopySection">
          <div class="mb-2 fw-semibold">
            Add A Hard Copy <span class="text-danger">(Recommended)</span>
          </div>
          <div class="d-flex gap-4 align-items-center flex-wrap">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="addIdCard" id="addIdYes" value="yes" style="border: 2px solid #6f7379;">
              <label class="form-check-label" for="addIdYes">
                Yes <span class="text-success">+$10.00</span>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="addIdCard" id="addIdNo" value="no" checked style="border: 2px solid #6f7379;">
              <label class="form-check-label" for="addIdNo">No</label>
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
          <button class="btn btn-outline-secondary" data-goto="4" style="border: 2px solid #6f7379; border-radius: 8px; padding: 10px 20px;background:#198754; color:#fff;">Back</button>
          <button class="btn" id="toWeltId" disabled style="background: #1AA3A9; color: white; border: none; border-radius: 8px; padding: 10px 20px;">Next</button>
        </div>
      </div>
    </section>

    <!-- STEP 6: WELT ID (CALIFORNIA ONLY) -->
    <section id="step-6" class="step d-none">
      <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
          <div class="card card-soft p-4">
            <img src="https://madical2.focussols.com/wp-content/uploads/2025/11/WhatsApp-Image-2025-11-11-at-21.43.11_684462b8.jpg" class="img-fluid rounded mb-3" alt="ID Card">
            <h3 class="mb-2 text-center" style="color: #1AA3A9;">Add a Wallet Copy ID Card?</h3>
            <p class="text-secondary text-center mb-3">
              Accepted at California dispensaries and replaces your paper recommendation for return visits.
            </p>
            <div class="border rounded-4 p-3 mb-3" style="border-color: #6f7379 !important;">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="wantsIdCard" style="border: 2px solid #6f7379;">
                <label class="form-check-label fw-semibold" for="wantsIdCard" style="color: #1AA3A9;">
                  Yes, add a Wallet ID Card (+$15)
                </label>
              </div>
            </div>
            <div class="d-flex justify-content-between">
              <button class="btn btn-outline-secondary" data-goto="5" style="background:#198754; color: #fff;border: 2px solid #6f7379; border-radius: 8px; padding: 10px 20px;">Back</button>
              <div class="d-grid gap-2 d-md-block">
                <button class="btn" id="idStepNext" style="background: #1AA3A9; color: white; border: none; border-radius: 8px; padding: 10px 20px;">Next</button>
                <button class="btn btn-link" id="idStepSkip" style="color: #1AA3A9;">Skip without ID card</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- STEP 7: OTHER STATES PLANS -->
    <section id="step-7" class="step d-none">
      <div class="card card-soft p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h4 class="mb-0" style="color: #1AA3A9;">Choose Your Plan</h4>
          <div><a class="link-secondary small me-3" href="#" id="logoutLink2" style="color: #1AA3A9;">Logout</a></div>
        </div>
        <div class="row g-3" id="otherStatesPlansGrid"></div>
        <div class="d-flex justify-content-between mt-4">
          <button class="btn btn-outline-secondary" data-goto="4" style="border: 2px solid #6f7379; border-radius: 8px; padding: 10px 20px;">Back</button>
          <button class="btn" id="toPayment" disabled style="background: #1AA3A9; color: white; border: none; border-radius: 8px; padding: 10px 20px;">Next</button>
        </div>
      </div>
    </section>

    <!-- STEP 8: DURATION -->
    <section id="step-8" class="step d-none">
      <div class="card card-soft p-4 p-md-5">
        <h2 class="text-center mb-1" style="color: #1AA3A9;">How long do you want access to medical cannabis?</h2>
        <p class="text-center text-secondary mb-4">
          Your certification must be renewed yearly. Prices depend on your plan/state.
        </p>
        <div class="row g-3 justify-content-center" id="durationGrid"></div>
        
        <!-- Security Message in HTML -->
        <div class="col-12 text-center mt-4">
          <div class="d-flex align-items-center justify-content-center gap-2" style="color: #198754;">
            <i class="bi bi-shield-check"></i>
            <span class="fw-semibold text-align-center">100% Secure & HIPAA-Compliant Checkout</span>
          
          </div>
        </div>
        
        <div class="d-flex justify-content-between mt-4">
          <button class="btn btn-outline-secondary" id="durationBackBtn" style="background: #198754; border: 2px solid #198754;color: #fff; border-radius: 8px; padding: 10px 20px;">Back</button>
          <button class="btn" id="toReview" disabled style="background: #1AA3A9; color: white; border: none; border-radius: 8px; padding: 10px 20px;">Next</button>
        </div>
      </div>
    </section>

    <!-- STEP 9: REVIEW + PAYMENT -->
<section id="step-9" class="step d-none">
  <div class="card card-soft p-4 p-md-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0" style="color: #1AA3A9;">Review your order</h4>
      <div class="review-actions d-flex gap-2 flex-wrap" id="reviewActionsContainer">
        <!-- Dynamic content will be inserted here -->
      </div>
    </div>

    <div class="row g-5">
      <!-- Left Column - Order Summary -->
      <div class="col-lg-5">
        <div class="mb-4">
          <h5 class="mb-3" style="color: #1AA3A9;">Order Summary</h5>
          <ul class="list-group mb-3" id="orderBreakdown"></ul>
          
          <!-- User Information -->
          <div class="border rounded-4 p-3 mt-4" style="border-color: #6f7379 !important;">
            <h6 class="mb-3" style="color: #1AA3A9;">Your Information</h6>
            <div class="row g-2 mb-3">
              <div class="col-12 col-md-6">
                <label class="form-label small mb-1">First Name</label>
                <input type="text" class="form-control form-control-sm" id="reviewFirstName" 
                       placeholder="First Name" style="border: 1px solid #6f7379; border-radius: 6px; padding: 8px;">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label small mb-1">Last Name</label>
                <input type="text" class="form-control form-control-sm" id="reviewLastName" 
                       placeholder="Last Name" style="border: 1px solid #6f7379; border-radius: 6px; padding: 8px;">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Email Address</label>
              <input type="email" class="form-control form-control-sm" id="reviewEmail" 
                     placeholder="your@email.com" style="border: 1px solid #6f7379; border-radius: 6px; padding: 8px;">
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Phone Number</label>
              <input type="text" class="form-control form-control-sm" id="reviewPhone" 
                     placeholder="(123) 456-7890" maxlength="14" 
                     oninput="formatPhone(this)"
                     style="border: 1px solid #6f7379; border-radius: 6px; padding: 8px;">
            </div>
            <div class="d-grid">
              <button class="btn btn-sm" type="button" id="updateUserInfo" 
                      style="background: #198754; color: white; border: none; border-radius: 6px; padding: 8px; font-weight: 500;">
                <i class="bi bi-check-circle me-1"></i>Update Information
              </button>
            </div>
          </div>
          
          <!-- Total Price -->
          <div class="d-flex justify-content-between fw-semibold fs-5 border-top pt-3 mt-3">
            <span>Total</span><span id="orderTotal" style="color: #1AA3A9;">$0</span>
          </div>
          
          <!-- Security Notice -->
          <div class="alert alert-info mt-3" style="background-color: #E8F6F7; border-color: #1AA3A9; color: #334155;">
            <div class="d-flex align-items-center">
              <i class="bi bi-shield-lock me-2" style="color: #1AA3A9;"></i>
              <span class="small">Your payment is 100% secure & encrypted</span>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between flex-wrap gap-2 flex-column">
          <button class="btn btn-outline-secondary" data-goto="8" 
                  style="border: 2px solid #6f7379;background: #198754;color: #fff; border-radius: 8px; padding: 12px 24px; flex: 1; min-width: 120px;">
            Back
          </button>
          <button class="btn" id="omcPayNow" 
                  style="background: #1AA3A9; color: white; border: none; border-radius: 8px; padding: 12px 24px; font-weight: 600; flex: 1; min-width: 120px;">
            <i class="bi bi-lock me-2"></i>Pay Securely
          </button>
        </div>
      </div>

      <!-- Right Column - Payment Info -->
      <div class="col-lg-7">
        <div class="border rounded-4 p-4" style="border-color: #6f7379 !important;">
          <h4 class="mb-3" style="color: #1AA3A9;">Secure Payment Processing</h4>
          
          <div class="alert alert-success" style="background-color: #E8F6F7; border-color: #1AA3A9;">
            <div class="d-flex">
              <i class="bi bi-shield-check fs-4 me-3" style="color: #198754;"></i>
              <div>
                <h5 class="mb-2">100% Secure Payment</h5>
                <p class="mb-1">You will be redirected to Authorize.Net's secure payment page.</p>
                <p class="mb-0 small">No credit card information is stored on our servers.</p>
              </div>
            </div>
          </div>
          
          <div class="row mt-4">
            <div class="col-md-6">
              <div class="d-flex align-items-center mb-3">
                <i class="bi bi-credit-card fs-3 me-3" style="color: #1AA3A9;"></i>
                <div>
                  <h6 class="mb-1">Secure Encryption</h6>
                  <p class="small text-secondary mb-0">256-bit SSL encryption</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-center mb-3">
                <i class="bi bi-shield-check fs-3 me-3" style="color: #1AA3A9;"></i>
                <div>
                  <h6 class="mb-1">PCI Compliant</h6>
                  <p class="small text-secondary mb-0">Fully PCI DSS compliant</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-center mb-3">
                <i class="bi bi-lock fs-3 me-3" style="color: #1AA3A9;"></i>
                <div>
                  <h6 class="mb-1">Trusted Partner</h6>
                  <p class="small text-secondary mb-0">Powered by Authorize.Net</p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="d-flex align-items-center mb-3">
                <i class="bi bi-clock-history fs-3 me-3" style="color: #1AA3A9;"></i>
                <div>
                  <h6 class="mb-1">Instant Processing</h6>
                  <p class="small text-secondary mb-0">Real-time payment processing</p>
                </div>
              </div>
            </div>
          </div>
          
          <div class="mt-4">
            <h6>Accepted Payment Methods:</h6>
            <div class="d-flex gap-3 mt-2">
              <img src="https://img.icons8.com/color/48/visa.png" alt="Visa" height="40">
              <img src="https://img.icons8.com/color/48/mastercard.png" alt="Mastercard" height="40">
              <img src="https://img.icons8.com/color/48/american-express.png" alt="Amex" height="40">
              <img src="https://img.icons8.com/color/48/discover.png" alt="Discover" height="40">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
  </main>`;

  // ------------- UTILITIES --------------
  function toast(msg, type='success'){
    const t = document.createElement('div');
    t.className = `toast align-items-center text-bg-${type==='success'?'success':(type==='error'?'danger':'secondary')} border-0 show position-fixed bottom-0 end-0 m-3`;
    t.role = 'alert';
    t.style.zIndex = 2147483647;
    t.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 5000);
  }

  function ajaxPost(data){
    return new Promise((resolve, reject) => {
      jQuery.post(ajaxURL, data).done(res => resolve(res))
        .fail(xhr => {
          reject(xhr && xhr.responseJSON ? xhr.responseJSON : {
            success:false,
            data:{message:'Network/Server error'}
          });
        });
    });
  }

  // DOB setup
  function setupDOB(){
    const mSel = qs('[name="dobMonth"]');
    const dSel = qs('[name="dobDay"]');
    const ySel = qs('[name="dobYear"]');
    if (!mSel || !dSel || !ySel) return;

    if (ySel.options.length <= 1){
      const yr = new Date().getFullYear();
      for (let y = yr - 18; y >= yr - 100; y--){
        const opt = document.createElement('option');
        opt.value = opt.textContent = y;
        ySel.appendChild(opt);
      }
    }
    function daysInMonth(m, y){ return new Date(y, m, 0).getDate(); }
    function refreshDays(){
      const m = parseInt(mSel.value || '1', 10);
      const y = parseInt(ySel.value || (new Date().getFullYear()-18), 10);
      const max = daysInMonth(m || 1, y || 2000);
      const current = dSel.value;
      dSel.innerHTML = '<option value="" disabled selected>Day</option>';
      for (let d=1; d<=max; d++){
        const v = String(d).padStart(2,'0');
        const opt = document.createElement('option');
        opt.value = opt.textContent = v;
        dSel.appendChild(opt);
      }
      if (current && parseInt(current,10) <= max){
        dSel.value = String(current).padStart(2,'0');
      }
    }
    if (dSel.options.length <= 1){
      for (let d=1; d<=31; d++){
        const v = String(d).padStart(2,'0');
        const opt = document.createElement('option');
        opt.value = opt.textContent = v;
        dSel.appendChild(opt);
      }
    }
    mSel.addEventListener('change', refreshDays);
    ySel.addEventListener('change', refreshDays);
  }

  // Step navigation
  function showStep(n){
    const stepNum = parseInt(n, 10);

    qsa('.step').forEach(s => s.classList.add('d-none'));
    const stepEl = qs(`#step-${stepNum}`);
    if (stepEl) stepEl.classList.remove('d-none');

    if (stepNum === 4){
      setupDOB();
      setupSignaturePad();
    }

   // Update dynamic content for step 9
if (stepNum === 9) {
  updateReviewStep();
  populateUserInfoFields();
  
  // Setup SIM payment when step 9 is shown
  setTimeout(setupSIMPayment, 100);
}

    window.scrollTo({top:0, behavior:'smooth'});
  }

  // Populate user info fields in review step
  function populateUserInfoFields() {
    // Get name parts from app.user.name
    const nameParts = (app.user.name || '').split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || '';
    
    // Set values in review form
    const firstNameField = qs('#reviewFirstName');
    const lastNameField = qs('#reviewLastName');
    const emailField = qs('#reviewEmail');
    const phoneField = qs('#reviewPhone');
    
    if (firstNameField) firstNameField.value = firstName;
    if (lastNameField) lastNameField.value = lastName;
    if (emailField) emailField.value = app.user.email || '';
    if (phoneField) phoneField.value = app.user.phone || '';
    
    // Also update billing state
    const billingState = qs('#billing_state');
    const shippingState = qs('#shipping_state');
    if (billingState) billingState.value = app.state || '';
    if (shippingState) shippingState.value = app.state || '';
  }

  // Update user information from review form
  function updateUserInfoFromReview() {
    const firstName = (qs('#reviewFirstName')?.value || '').trim();
    const lastName = (qs('#reviewLastName')?.value || '').trim();
    const email = (qs('#reviewEmail')?.value || '').trim();
    const phone = (qs('#reviewPhone')?.value || '').trim();
    
    // Validation
    if (!firstName || !lastName) {
      toast('Please enter both first and last name', 'error');
      return false;
    }
    
    if (!email || !/\S+@\S+\.\S+/.test(email)) {
      toast('Please enter a valid email address', 'error');
      return false;
    }
    
    if (!phone || phone.replace(/\D/g, '').length < 10) {
      toast('Please enter a valid phone number', 'error');
      return false;
    }
    
    // Update app state
    app.user.name = `${firstName} ${lastName}`.trim();
    app.user.email = email;
    app.user.phone = phone;
    
    // Update patient header if we're on medical info step
    const patientHeader = qs('#patientHeader');
    if (patientHeader) {
      patientHeader.textContent = `${app.user.name}`;
    }
    
    // Also update medical form fields if needed
    const medFirstName = qs('#formMedical [name="firstName"]');
    const medLastName = qs('#formMedical [name="lastName"]');
    const medEmail = qs('#formMedical [name="email"]');
    const medPhone = qs('#formMedical [name="phone"]');
    
    if (medFirstName) medFirstName.value = firstName;
    if (medLastName) medLastName.value = lastName;
    if (medEmail) medEmail.value = email;
    if (medPhone) medPhone.value = phone;
    
    return true;
  }

  // Update review step with dynamic content
  function updateReviewStep() {
    const reviewActionsContainer = qs('#reviewActionsContainer');
    if (reviewActionsContainer) {
      reviewActionsContainer.innerHTML = getReviewActionsHTML();
    }
  }

  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-goto]');
    if (btn){
      e.preventDefault();
      const targetStep = btn.getAttribute('data-goto');
      showStep(targetStep);
    }
  });

  // Theme toggle
  (function initTheme(){
    const t   = qs('#themeToggle');
    const key = 'omc-theme';
    const saved = localStorage.getItem(key);
    if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
    t?.addEventListener('click', () => {
      const cur  = document.documentElement.getAttribute('data-bs-theme') || 'light';
      const next = cur === 'light' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-bs-theme', next);
      localStorage.setItem(key, next);
      t.setAttribute('aria-pressed', String(next === 'dark'));
    });
  })();

  // Password toggle functionality
  document.addEventListener('click', e => {
    const t = e.target;
    if (!t) return;

    if (t.id === 'togglePass' || t.closest('#togglePass')){
      const ip = qs('#formLogin [name="password"]');
      ip.type = ip.type === 'password' ? 'text' : 'password';
    }

    if (t.id === 'toggleRegPassword' || t.closest('#toggleRegPassword')){
      const ip = qs('#formRegister [name="regPassword"]');
      ip.type = ip.type === 'password' ? 'text' : 'password';
    }

    if (t.id === 'toggleConfirmPassword' || t.closest('#toggleConfirmPassword')){
      const ip = qs('#formRegister [name="confirmPassword"]');
      ip.type = ip.type === 'password' ? 'text' : 'password';
    }

    if (t.id === 'forgotPassLink'){
      e.preventDefault();
      if (lostPassURL){
        window.location.href = lostPassURL;
      } else {
        toast('Password reset page is not configured.', 'error');
      }
    }
    
    // Update User Info button
    if (t.id === 'updateUserInfo' || t.closest('#updateUserInfo')) {
      e.preventDefault();
      if (updateUserInfoFromReview()) {
        toast('Information updated successfully!', 'success');
      }
    }
  });

  // Shipping address toggle
  qs('#differentShipping')?.addEventListener('change', function() {
    const shippingSection = qs('#shippingAddressSection');
    if (this.checked) {
      shippingSection.classList.remove('d-none');
      qsa('#shippingAddressSection input, #shippingAddressSection select').forEach(field => {
        field.required = true;
      });
    } else {
      shippingSection.classList.add('d-none');
      qsa('#shippingAddressSection input, #shippingAddressSection select').forEach(field => {
        field.required = false;
      });
    }
  });

  qs('#loginTopBtn').addEventListener('click', () => showStep(2));

  function validateForm(f){
    if (!f) return false;
    f.classList.add('was-validated');
    return f.checkValidity();
  }

  // REGISTER (STEP 1)
  qs('#formRegister').addEventListener('submit', function(e){
    e.preventDefault();
    if (!validateForm(this)) return;

    const f = this;
    const password = f.regPassword.value;
    const confirmPassword = f.confirmPassword.value;

    if (password !== confirmPassword) {
      toast('Passwords do not match', 'error');
      f.confirmPassword.classList.add('is-invalid');
      return;
    } else {
      f.confirmPassword.classList.remove('is-invalid');
    }

    const post = {
      action:   'omc_register',
      nonce:    nonce,
      email:    f.regEmail.value,
      password: password,
      firstName: f.firstName.value,
      lastName: '',
      phone:    '',
      state:    '',
      address:  ''
    };

    ajaxPost(post).then(res => {
      if (!res || !res.success){
        const msg = (res && res.data && res.data.message) || 'Registration failed';
        toast(msg, 'error');

        if (msg && msg.indexOf('Email already registered') !== -1) {
          const loginForm = qs('#formLogin');
          if (loginForm) {
            loginForm.email.value = f.regEmail.value;
          }
          showStep(2);
        }
        return;
      }

      app.user.name    = f.firstName.value.trim();
      app.user.email   = f.regEmail.value;
      app.user.phone   = '';
      app.user.address = '';

      const badge = qs('#userNameBadge');
      if (badge){
        badge.textContent = app.user.email;
        badge.classList.remove('d-none');
      }
      if (qs('#confirmEmail')) qs('#confirmEmail').textContent = app.user.email;

      showStep(3);
    }).catch(() => {
      toast('Registration error', 'error');
    });
  });

  // LOGIN
  qs('#formLogin').addEventListener('submit', function(e){
    e.preventDefault();
    if (!validateForm(this)) return;

    const post = {
      action:  'omc_login',
      nonce,
      email:   this.email.value,
      password:this.password.value,
      remember: qs('#rememberMe')?.checked ? 1 : 0
    };

    ajaxPost(post).then(res => {
      if (!res || !res.success){
        toast((res && res.data && res.data.message) || 'Login failed', 'error');
        return;
      }

      const email = this.email.value;
      app.user.email = email;

      qs('#userNameBadge').textContent = email;
      qs('#userNameBadge').classList.remove('d-none');

      if (qs('#confirmEmail')) qs('#confirmEmail').textContent = app.user.email;

      showStep(3);
    }).catch(() => {
      toast('Login error', 'error');
    });
  });

  // Confirm contact
  qs('#confirmYes').addEventListener('click', () => {
    qs('#patientHeader').textContent = `${app.user.name}`;
    showStep(4); // Go to medical info
  });

  // Medical Info Form Submit - UPDATED FLOW
  qs('#formMedical').addEventListener('submit', function(e){
    e.preventDefault();
    if (!validateForm(this)) return;

    // Update user info from medical form
    const firstName = this.firstName.value.trim();
    const lastName = this.lastName.value.trim();
    app.user.name = `${firstName} ${lastName}`.trim();
    app.user.phone = this.phone.value;
    app.user.email = this.email.value;
    app.state = this.state.value;

    // Update patient header
    qs('#patientHeader').textContent = `${app.user.name}`;

    // If California, show California plans (step 5)
    if (app.state === 'California') {
      renderCaliforniaPlans();
      showStep(5);
    } else {
      // For other states, set the basic plan and go directly to duration selection (step 8)
      const basePrice = STATE_BASE_1Y[app.state] || 149.99;
      app.plan = {
        name: 'New Patient Certification',
        price: basePrice
      };
      renderDurations();
      showStep(8);
    }
  });

  // Render California Plans - RESPONSIVE DESIGN
  function renderCaliforniaPlans(){
    const grid = qs('#californiaPlansGrid');
    grid.innerHTML = '';

    const plans = [
      {
        price: 64.99,
        name: 'Special Offer',
        desc: 'Instant PDF + Hard Copy With Embossed Seal + Patient ID Card',
        recommended: true,
        hcEligible: false,
        saveBadge: true,
        saveText: 'Save 35%'
      },
      {
        price: 99.99,
        name: 'Caregiver',
        desc: 'New Patient (Caregiver) — Caregiver + New Patient',
        hcEligible: true
      },
      {
        price: 189.99,
        name: 'Growers Plan',
        desc: 'License to Cultivate up to 99 Plants',
        hcEligible: true
      },
      {
        price: 39.99,
        name: 'New Patient PDF',
        desc: 'New Patient – PDF Only',
        hcEligible: true
      },
      {
        price: 49.99,
        name: 'Renewal PDF',
        desc: 'Existing patient renewal – With Hard Copy',
        hcEligible: false
      }
    ];

    plans.forEach(p => {
      const col = document.createElement('div');
      col.className = 'col-12 col-md-6 col-lg-4 mb-3';
      col.innerHTML = `
        <div class="p-3 border rounded-4 option-card h-100 position-relative"
             data-price="${p.price}" data-plan="${p.name}" data-hc="${p.hcEligible ? '1' : '0'}">
          ${p.recommended ? '<span class="badge text-bg-info badge-reco">Recommended</span>' : ''}
          ${p.saveBadge ? `<span class="badge" style="background: #198754; color: white;">${p.saveText}</span>` : ''}
          <div class="form-check h-100 d-flex flex-column">
            <div class="d-flex align-items-center mb-2">
              <input class="form-check-input me-2" type="radio" name="caPlan" required>
              <label class="form-check-label fw-semibold fs-5">$${p.price.toFixed(2)}</label>
            </div>
            <div class="text-secondary flex-grow-1 small">${p.desc}</div>
          </div>
        </div>`;
      grid.appendChild(col);
    });

    // Add click handlers for California plan selection
    qsa('#californiaPlansGrid .option-card').forEach(card => {
      card.addEventListener('click', () => {
        qsa('#californiaPlansGrid .option-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        card.querySelector('input[type=radio]').checked = true;

        app.plan = {
          name: card.dataset.plan,
          price: parseFloat(card.dataset.price)
        };
        
        // Enable next button
        qs('#toWeltId').disabled = false;

        // Handle hard copy section visibility
        const hcSection = qs('#hardCopySection');
        if (hcSection) {
          const eligible = card.dataset.hc === '1';
          if (eligible) {
            hcSection.classList.remove('d-none');
          } else {
            hcSection.classList.add('d-none');
            app.addHardCopy = false;
            const yes = qs('#addIdYes'), no = qs('#addIdNo');
            if (yes) yes.checked = false;
            if (no) no.checked = true;
          }
        }
        
        computeOrder();
      });
    });

    // Auto-select recommended plan for California
    const recommendedCard = document.querySelector('.badge-reco')?.closest('.option-card');
    if (recommendedCard) {
      recommendedCard.click();
    }
  }

  // Render Other States Plans - RESPONSIVE DESIGN
  function renderOtherStatesPlans(){
    const grid = qs('#otherStatesPlansGrid');
    grid.innerHTML = '';

    const state = app.state;
    const basePrice = STATE_BASE_1Y[state] ?? 149.99;

    const plans = [
      {
        price: basePrice,
        name: 'New Patient Certification',
        desc: 'Complete medical cannabis certification for new patients',
        recommended: true
      }
    ];

    plans.forEach(p => {
      const col = document.createElement('div');
      col.className = 'col-12 col-md-8 col-lg-6 mb-3';
      col.innerHTML = `
        <div class="p-3 border rounded-4 option-card h-100 position-relative"
             data-price="${p.price}" data-plan="${p.name}">
          ${p.recommended ? '<span class="badge text-bg-info badge-reco">Recommended</span>' : ''}
          <div class="form-check h-100 d-flex flex-column">
            <div class="d-flex align-items-center mb-2">
              <input class="form-check-input me-2" type="radio" name="otherPlan" required>
              <label class="form-check-label fw-semibold fs-5">$${p.price.toFixed(2)}</label>
            </div>
            <div class="text-secondary flex-grow-1 small">${p.desc}</div>
          </div>
        </div>`;
      grid.appendChild(col);
    });

    // Add click handlers for other states plan selection
    qsa('#otherStatesPlansGrid .option-card').forEach(card => {
      card.addEventListener('click', () => {
        qsa('#otherStatesPlansGrid .option-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        card.querySelector('input[type=radio]').checked = true;

        app.plan = {
          name: card.dataset.plan,
          price: parseFloat(card.dataset.price)
        };
        
        // Enable next button
        qs('#toPayment').disabled = false;
        computeOrder();
      });
    });

    // Auto-select the only plan for other states
    const firstCard = document.querySelector('#otherStatesPlansGrid .option-card');
    if (firstCard) {
      firstCard.click();
    }
  }

  // California plan next button
  qs('#toWeltId').addEventListener('click', function(e) {
    e.preventDefault();
    if (!app.plan) {
      toast('Please select a plan first.', 'error');
      return;
    }
    showStep(6); // Go to Welt ID step for California
  });

  // Other states plan next button
  qs('#toPayment').addEventListener('click', function(e) {
    e.preventDefault();
    if (!app.plan) {
      toast('Please select a plan first.', 'error');
      return;
    }
    
    // For other states, show duration selection (step 8)
    renderDurations();
    showStep(8);
  });

  qsa('[name="addIdCard"]').forEach(r =>
    r.addEventListener('change', () => {
      app.addHardCopy = qs('#addIdYes').checked;
      computeOrder();
    })
  );

  document.addEventListener('click', e => {
    if (e.target && e.target.id === 'takePicture'){
      qs('#idUpload')?.click();
    }
  });

  (function setupOtherCondition(){
    const otherChk  = qs('#cnd12');
    const otherWrap = qs('#otherConditionWrap');
    if (!otherChk || !otherWrap) return;
    otherChk.addEventListener('change', () => {
      if (otherChk.checked){
        otherWrap.classList.remove('d-none');
      } else {
        otherWrap.classList.add('d-none');
        const ta = otherWrap.querySelector('textarea');
        if (ta) ta.value = '';
      }
    });
  })();

  qs('#wantsIdCard').addEventListener('change', e => {
    app.addIdCard = e.target.checked;
    computeOrder();
  });
  
  qs('#idStepNext').addEventListener('click', () => {
    renderDurations();
    showStep(8);
  });
  
  qs('#idStepSkip').addEventListener('click', () => {
    app.addIdCard = false;
    qs('#wantsIdCard').checked = false;
    computeOrder();
    renderDurations();
    showStep(8);
  });

  function discountedTotals(base){
    const reg1 = base;
    const reg2 = base * 2;
    const reg3 = base * 3;

    const price1 = reg1;
    const price2 = reg2 * (1 - DISCOUNT.y2);
    const price3 = reg3 * (1 - DISCOUNT.y3);

    return [
      { label:'3 years', years:3, price:price3, regular:reg3, badge:'Most Popular' },
      { label:'2 years', years:2, price:price2, regular:reg2 },
      { label:'1 year',  years:1, price:price1, regular:reg1 }
    ];
  }

  function renderDurations(){
    const grid = qs('#durationGrid');
    grid.innerHTML = '';
    if (!app.plan){
      qs('#toReview').disabled = true;
      return;
    }
    
    // For other states, use the state base price
    const base = app.state === 'California' ? Number(app.plan.price || 0) : STATE_BASE_1Y[app.state] || 149.99;
    const rows = discountedTotals(base);

    rows.forEach(r => {
      const save = Math.max(0, r.regular - r.price);
      const durationId = `duration-${r.years}`;
      grid.insertAdjacentHTML('beforeend', `
        <div class="col-12 col-md-8">
          <div class="p-3 border rounded-4 option-card"
               data-price="${r.price}" data-full="${r.regular}" data-save="${save}"
               data-years="${r.years}" data-plan="${r.label}">
            <div class="d-flex align-items-center justify-content-between">
              <div class="d-flex align-items-center gap-2">
                <input class="form-check-input" type="radio" name="duration" id="${durationId}" required>
                <label class="form-check-label fw-semibold" for="${durationId}">${r.label}</label>
              </div>
              ${r.badge ? '<span class="badge text-bg-primary">Most Popular</span>' : ''}
            </div>
            <div class="text-secondary">
              $${r.price.toFixed(2)} total
              ${save > 0 ? `<span class="text-success">(Save $${save.toFixed(2)})</span>` : ''}
            </div>
            <div class="small text-secondary">Regular $${r.regular.toFixed(2)}</div>
          </div>
        </div>`);
    });

    qsa('#durationGrid .option-card').forEach(card => {
      card.addEventListener('click', () => {
        qsa('#durationGrid .option-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        const radio = card.querySelector('input[type=radio]');
        radio.checked = true;
        app.duration = {
          name:  card.dataset.plan,
          price: parseFloat(card.dataset.price),
          years: parseInt(card.dataset.years,10) || 1,
          full:  parseFloat(card.dataset.full)  || parseFloat(card.dataset.price),
          save:  parseFloat(card.dataset.save)  || 0
        };
        qs('#toReview').disabled = false;
        computeOrder();
      });
    });

    const popular   = document.querySelector('#durationGrid .option-card .badge');
    const firstCard = document.querySelector('#durationGrid .option-card');
    (popular ? popular.closest('.option-card') : firstCard)?.click();
  }

  document.addEventListener('click', e => {
    if (e.target && e.target.id === 'toReview'){
      e.preventDefault();
      if (!app.duration){
        toast('Please select a duration first.', 'error');
        return;
      }
      computeOrder();
      showStep(9); // Go to payment for both California and other states
    }
  });

  // Duration back button handler
  qs('#durationBackBtn').addEventListener('click', function() {
    if (app.state === 'California') {
      showStep(6); // Go back to Welt ID for California
    } else {
      showStep(4); // Go back to Medical Form for other states
    }
  });

  // Mobile layout improvements
  function handleMobileLayout() {
    const isMobile = window.innerWidth < 768;
    
    if (isMobile) {
      // Adjust button layouts for mobile
      const actionButtons = document.querySelectorAll('.d-flex.justify-content-between');
      actionButtons.forEach(container => {
        if (container.querySelectorAll('.btn').length > 1) {
          container.classList.add('flex-column', 'gap-2');
          container.querySelectorAll('.btn').forEach(btn => {
            btn.classList.add('w-100');
          });
        }
      });

      // Adjust plan cards for mobile
      const planCards = document.querySelectorAll('.option-card');
      planCards.forEach(card => {
        card.style.marginBottom = '1rem';
      });
      
      // Adjust user info fields for mobile
      const userInfoContainer = qs('.border.rounded-4.p-3.mt-4');
      if (userInfoContainer) {
        userInfoContainer.classList.add('p-2');
      }
    }
  }

  // Call on load and resize
  window.addEventListener('load', handleMobileLayout);
  window.addEventListener('resize', handleMobileLayout);

  // Coupon (SAVE10 -> 100% OFF)
  qs('#applyCoupon').addEventListener('click', () => {
    const code = (qs('#couponCode').value || '').trim().toUpperCase();
    const msg  = qs('#couponMsg');

    if (!code){
      msg.textContent = 'Enter a coupon code.';
      msg.className = 'small text-danger mt-1';
      msg.classList.remove('d-none');
      app.coupon.applied = false;
      app.coupon.code    = '';
      computeOrder();
      return;
    }

    if (code === 'SAVE10'){
      app.coupon.applied = true;
      app.coupon.code    = code;
      app.coupon.percent = 100;
      msg.textContent = 'Coupon applied: 100% OFF';
      msg.className   = 'small text-success mt-1';
      msg.classList.remove('d-none');
    } else {
      app.coupon.applied = false;
      app.coupon.code    = '';
      msg.textContent = 'Invalid code.';
      msg.className   = 'small text-danger mt-1';
      msg.classList.remove('d-none');
    }
    computeOrder();
  });

  function computeOrder(){
    const list = qs('#orderBreakdown');
    if (!list) return;

    list.innerHTML = '';
    let subtotal = 0;
    const years = app.duration ? (app.duration.years || 1) : 1;

    const add = (label, price, isDiscount = false, smallNote = '') => {
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex justify-content-between align-items-start';
      li.innerHTML = `
        <div>
          <div>${label}</div>
          ${smallNote ? `<div class="small text-secondary">${smallNote}</div>` : ''}
        </div>
        <span class="${isDiscount ? 'text-success' : ''}">
          ${isDiscount ? '-' : ''}${fmt(price)}
        </span>`;
      list.appendChild(li);
    };

    if (app.plan && !app.duration){
      add(app.plan.name, app.plan.price);
      subtotal += app.plan.price;
    }

    if (app.duration){
      const { name, price, years: yrs, full, save } = app.duration;
      const note = yrs > 1 ? `Regular ${fmt(full)} — Savings ${fmt(save)}` : '';
      add(name, price, false, note);
      subtotal += price;
    }

    if (app.addHardCopy){
      const hcTotal = 10 * years;
      add('Hard Copy', hcTotal, false, years > 1 ? `${years} years` : '');
      subtotal += hcTotal;
    }

    if (app.addIdCard){
      const idTotal = 15 * years;
      add('Welt ID Card', idTotal, false, years > 1 ? `${years} years` : '');
      subtotal += idTotal;
    }

    if (app.coupon.applied){
      const disc = +(subtotal * (app.coupon.percent / 100)).toFixed(2);
      add(`Coupon (${app.coupon.percent}% OFF)`, disc, true);
      subtotal -= disc;
    }

    qs('#orderTotal').textContent = fmt(subtotal);
  }

  function getTotal(){
    return parseFloat((qs('#orderTotal').textContent || '0').replace('$','')) || 0;
  }

  function luhn(num){
    let s = 0, a = false;
    for (let i = num.length - 1; i >= 0; i--){
      let n = +num[i];
      if (a){
        n *= 2;
        if (n > 9) n -= 9;
      }
      s += n;
      a = !a;
    }
    return s % 10 === 0;
  }

  // FIXED: Check if Accept.js is properly loaded
  function isAcceptJSReady() {
    return window.Accept && typeof window.Accept.dispatchData === 'function';
  }


  // FIXED: PAY NOW (Correct Accept.js tokenization)
  // qs('#omcPayNow').addEventListener('click', function(){
  //   const name = qs('#dc_name').value.trim();
  //   const numRaw  = qs('#dc_number').value || '';
  //   const num  = numRaw.replace(/\D/g, '');
  //   const exp  = qs('#dc_exp').value.trim();
  //   const cvv  = qs('#dc_cvv').value.trim();
  //   const zip  = qs('#billing_zip').value.trim(); // FIXED: Use billing_zip

  //   // Validate payment form
  //   if (!name) {
  //     toast('Please enter the name on card', 'error');
  //     qs('#dc_name').focus();
  //     return;
  //   }

  //   if (!num || num.length < 13 || !luhn(num)) {
  //     toast('Please enter a valid card number', 'error');
  //     qs('#dc_number').focus();
  //     return;
  //   }

  //   if (!/^\d{2}\/\d{2}$/.test(exp)) {
  //     toast('Please enter valid expiration date (MM/YY)', 'error');
  //     qs('#dc_exp').focus();
  //     return;
  //   }

  //   if (!/^\d{3,4}$/.test(cvv)) {
  //     toast('Please enter valid CVV', 'error');
  //     qs('#dc_cvv').focus();
  //     return;
  //   }

  //   if (!zip || zip.length < 3) {
  //     toast('Please enter valid ZIP code', 'error');
  //     qs('#billing_zip').focus();
  //     return;
  //   }

  //   // Validate user info is updated
  //   if (!app.user.name || !app.user.email || !app.user.phone) {
  //     toast('Please update your information before payment', 'error');
  //     return;
  //   }

  //   const amount = getTotal();
  //   if (!amount || amount <= 0){
  //     toast('Total amount is invalid.', 'error');
  //     return;
  //   }

  //   const [mm, yy] = exp.split('/');

  //   if (!settings || !settings.client_key || !settings.api_login){
  //     toast('Payment gateway is not configured. Please contact support.', 'error');
  //     return;
  //   }

  //   // Check if Accept.js is loaded
  //   if (!isAcceptJSReady()) {
  //     toast('Payment system is still loading. Please try again in a few seconds.', 'error');
  //     console.error('Accept.js not loaded or incorrect. Check Accept.js script loading.');
  //     return;
  //   }

  //   const payBtn = qs('#omcPayNow');
  //   const originalText = payBtn.innerHTML;
  //   payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
  //   payBtn.disabled = true;

  //   // CORRECT Accept.js implementation
  //   const secureData = {
  //     authData: {
  //       clientKey: settings.client_key,
  //       apiLoginID: settings.api_login
  //     },
  //     cardData: {
  //       cardNumber: num,
  //       month: mm,
  //       year: `20${yy}`,
  //       cardCode: cvv,
  //       fullName: name,
  //       zip: zip
  //     }
  //   };

  //   console.log('Sending to Accept.js:', secureData);

  //   // CORRECT: dispatchData takes secureData object and callback
  //   Accept.dispatchData(secureData, function(response){
  //     console.log('Accept.js Response:', response);

  //     if (!response || response.messages.resultCode === 'Error'){
  //       const msg = response && response.messages &&
  //                   response.messages.message &&
  //                   response.messages.message[0]
  //                   ? response.messages.message[0].text
  //                   : 'Payment tokenization failed.';
        
  //       payBtn.innerHTML = originalText;
  //       payBtn.disabled = false;
  //       toast(msg, 'error');
  //       return;
  //     }

  //     const opaque      = response.opaqueData.dataValue;
  //     const descriptor  = response.opaqueData.dataDescriptor;

  //     console.log('Token received. Sending to server...', { opaque, descriptor });

  //     ajaxPost({
  //       action: 'omc_process_payment',
  //       nonce,
  //       opaque,
  //       descriptor,
  //       amount
  //     }).then(res => {
  //       payBtn.innerHTML = originalText;
  //       payBtn.disabled = false;
        
  //       if (!res || !res.success){
  //         const errorMsg = (res && res.data && res.data.message) || 'Payment failed';
  //         toast(errorMsg, 'error');
  //         console.error('Payment Error Response:', res);
  //         return;
  //       }

  //       const txId = res.data && res.data.transaction_id ? res.data.transaction_id : '';
  //       toast('Payment successful! Transaction ID: ' + txId, 'success');

  //       const order = {
  //         user: app.user,
  //         state: app.state,
  //         plan: app.plan,
  //         duration: app.duration,
  //         addHardCopy: app.addHardCopy,
  //         addIdCard:   app.addIdCard,
  //         signature:   app.signatureData || '',
  //         total: amount,
  //         transactionId: txId,
  //         billing: {
  //           name: name,
  //           street: qs('#billing_street').value,
  //           city: qs('#billing_city').value,
  //           state: qs('#billing_state').value,
  //           zip: zip,
  //           country: qs('#billing_country').value
  //         }
  //       };

  //       // Send admin notification
  //       ajaxPost({
  //         action: 'omc_notify_admin',
  //         nonce,
  //         payload: JSON.stringify(order)
  //       }).then(() => {
  //         toast('Order details emailed to admin.', 'success');
  //         // Optionally redirect to thank you page
  //         // setTimeout(() => { window.location.href = '/thank-you'; }, 3000);
  //       }).catch(() => {
  //         toast('Order email error', 'error');
  //       });

  //     }).catch((error) => {
  //       payBtn.innerHTML = originalText;
  //       payBtn.disabled = false;
  //       toast('Payment processing error. Please try again.', 'error');
  //       console.error('AJAX Error:', error);
  //     });
  //   });
  // });

// SIM PAYMENT - Setup function (will be called when step 9 is shown)
function setupSIMPayment() {
    const payBtn = qs('#omcPayNow');
    
    if (!payBtn) {
        console.log('Payment button not found - not on step 9');
        return;
    }
    
    // Remove any existing event listeners
    const newBtn = payBtn.cloneNode(true);
    payBtn.parentNode.replaceChild(newBtn, payBtn);
    
    // Add new event listener
    newBtn.addEventListener('click', async function(e){
        e.preventDefault();
        const payBtn = this;
        const originalText = payBtn.innerHTML;
        
        console.log('Payment button clicked - SIM Method');
        
        // Validate user info is updated
        if (!app.user.name || !app.user.email || !app.user.phone) {
            toast('Please update your information before payment', 'error');
            return;
        }

        const amount = getTotal();
        console.log('Payment amount:', amount);
        
        if (!amount || amount <= 0){
            toast('Total amount is invalid.', 'error');
            return;
        }

        // Show loading
        payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Preparing Payment...';
        payBtn.disabled = true;

        try {
            // Generate fingerprint for Authorize.Net SIM
            const sequence = Math.floor(Math.random() * 1000) + 1;
            const timestamp = Math.floor(Date.now() / 1000);
            
            console.log('Fetching fingerprint...', { amount, sequence, timestamp });
            
            const response = await fetch(ajaxURL, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'omc_generate_fingerprint',
                    nonce: nonce,
                    amount: amount,
                    sequence: sequence,
                    timestamp: timestamp
                })
            });
            
            const data = await response.json();
            console.log('Fingerprint response:', data);
            
            if (!data.success) {
                throw new Error(data.message || 'Payment setup failed');
            }
            
            // Create hidden form and submit (redirects to Authorize.Net)
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'https://test.authorize.net/gateway/transact.dll'; // TEST for local
            form.style.display = 'none';
            
            // Required fields for Authorize.Net SIM
            const fields = {
                'x_login': data.api_login,
                'x_amount': data.amount,
                'x_description': 'Medical Card Purchase - Medcardsnow',
                'x_invoice_num': 'INV-' + Date.now(),
                'x_fp_sequence': data.sequence,
                'x_fp_timestamp': data.timestamp,
                'x_fp_hash': data.fingerprint,
                'x_test_request': 'TRUE', // TRUE for testing
                'x_show_form': 'PAYMENT_FORM',
                'x_type': 'AUTH_CAPTURE',
                'x_method': 'CC',
                'x_relay_response': 'TRUE',
                'x_relay_url': ajaxURL + '?action=omc_sim_response',
                'x_relay_always': 'TRUE',
                'x_version': '3.1',
                'x_currency_code': 'USD',
                
                // Customer information
                'x_first_name': app.user.name.split(' ')[0] || '',
                'x_last_name': app.user.name.split(' ').slice(1).join(' ') || '',
                'x_email': app.user.email || '',
                'x_phone': app.user.phone || '',
                'x_state': app.state || '',
                
                // Additional settings
                'x_cancel_url': window.location.href,
                'x_cancel_url_text': 'Return to Medcardsnow'
            };
            
            console.log('Form fields:', fields);
            
            // Add all fields to form
            for (const [key, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            // Add form to page and submit
            document.body.appendChild(form);
            toast('Redirecting to secure payment page...', 'info');
            console.log('Submitting form to Authorize.Net...');
            form.submit();
            
        } catch (error) {
            console.error('Payment Error:', error);
            payBtn.innerHTML = originalText;
            payBtn.disabled = false;
            toast('Payment setup failed: ' + error.message, 'error');
        }
    });
    
    console.log('SIM Payment button setup complete');
}
  // Signature pad
  function setupSignaturePad(){
    if (sigPadInitialized) return;
    sigPadInitialized = true;

    const canvas   = qs('#sigCanvas');
    const clearBtn = qs('#clearSig');
    const saveBtn  = qs('#saveSig');
    const savedMsg = qs('#sigSavedMsg');
    if (!canvas || !clearBtn || !saveBtn) return;

    canvas.style.touchAction = 'none';

    const ctx = canvas.getContext('2d');
    let drawing = false;
    let lastX = 0, lastY = 0;

    function resizeCanvas(){
      const rect = canvas.getBoundingClientRect();
      const dataUrl = (canvas.width && canvas.height) ? canvas.toDataURL() : null;

      const width  = rect.width || canvas.clientWidth || 600;
      let height   = rect.height || canvas.clientHeight || 0;
      if (!height || height < 120){
        height = 150;
      }

      canvas.width  = width;
      canvas.height = height;

      ctx.lineWidth   = 2;
      ctx.lineCap     = 'round';
      ctx.strokeStyle = '#000';

      if (dataUrl){
        const img = new Image();
        img.onload = function(){ ctx.drawImage(img, 0, 0); };
        img.src = dataUrl;
      }
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function getPosFromEvent(e){
      const rect = canvas.getBoundingClientRect();
      let point = e;

      if (e.touches && e.touches.length){
        point = e.touches[0];
      } else if (e.changedTouches && e.changedTouches.length){
        point = e.changedTouches[0];
      }

      return {
        x: point.clientX - rect.left,
        y: point.clientY - rect.top
      };
    }

    function startDraw(e){
      e.preventDefault();
      const pos = getPosFromEvent(e);
      drawing = true;
      lastX = pos.x;
      lastY = pos.y;
    }

    function draw(e){
      if (!drawing) return;
      e.preventDefault();
      const pos = getPosFromEvent(e);
      ctx.beginPath();
      ctx.moveTo(lastX, lastY);
      ctx.lineTo(pos.x, pos.y);
      ctx.stroke();
      lastX = pos.x;
      lastY = pos.y;
    }

    function endDraw(e){
      if (!drawing) return;
      e.preventDefault();
      drawing = false;
    }

    if (window.PointerEvent){
      canvas.addEventListener('pointerdown', e => {
        canvas.setPointerCapture(e.pointerId);
        startDraw(e);
      });
      canvas.addEventListener('pointermove', draw);
      canvas.addEventListener('pointerup', e => {
        endDraw(e);
        canvas.releasePointerCapture(e.pointerId);
      });
      canvas.addEventListener('pointercancel', endDraw);
    } else {
      canvas.addEventListener('mousedown', startDraw);
      canvas.addEventListener('mousemove', draw);
      canvas.addEventListener('mouseup', endDraw);
      canvas.addEventListener('mouseleave', endDraw);

      canvas.addEventListener('touchstart', startDraw, { passive:false });
      canvas.addEventListener('touchmove', draw, { passive:false });
      canvas.addEventListener('touchend', endDraw);
    }

    clearBtn.addEventListener('click', function(){
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      if (savedMsg) savedMsg.classList.add('d-none');
      app.signatureData = '';
    });

    saveBtn.addEventListener('click', function(){
      app.signatureData = canvas.toDataURL('image/png');
      if (savedMsg){
        savedMsg.classList.remove('d-none');
        setTimeout(() => savedMsg.classList.add('d-none'), 2000);
      }
    });
  }

  // Add CSS for better mobile experience and responsive plan cards
  const style = document.createElement('style');
  style.textContent = `
    .badge-reco {
      background: #0dcaf0 !important;
    }

    .option-card {
      cursor: pointer;
      transition: all 0.2s ease;
      border: 2px solid #6f7379;
    }

    .option-card:hover {
      border-color: #1AA3A9;
    }

    .option-card.active {
      border-color: #1AA3A9;
      background-color: #E8F6F7;
    }

    /* Save badge styling */
    .badge:not(.badge-reco) {
      background: #198754 !important;
      color: white !important;
      font-weight: 600;
    }

    /* Fix radio button visibility */
    .form-check-input {
      border: 2px solid #6f7379 !important;
      background-color: white;
    }

    .form-check-input:checked {
      background-color: #1AA3A9;
      border-color: #1AA3A9 !important;
    }

    .form-check-input:focus {
      box-shadow: 0 0 0 0.2rem rgba(26, 163, 169, 0.25);
      border-color: #1AA3A9 !important;
    }

    /* Mobile responsive improvements */
    @media (max-width: 768px) {
      .option-card {
        margin-bottom: 1rem;
      }
      
      .d-flex.justify-content-between .btn {
        width: 100%;
        margin-bottom: 0.5rem;
      }
      
      .review-actions .btn {
        width: 100%;
        margin-bottom: 0.5rem;
      }
      
      #californiaPlansGrid .col-md-6,
      #otherStatesPlansGrid .col-md-8 {
        margin-bottom: 1rem;
      }
      
      .option-card .text-secondary {
        font-size: 0.875rem;
      }
      
      /* User info form on mobile */
      #reviewFirstName, #reviewLastName {
        font-size: 14px;
      }
      
      .border.rounded-4.p-3.mt-4 {
        padding: 1rem !important;
      }
      
      /* Payment form adjustments */
      #dc_name, #dc_number, #dc_exp, #dc_cvv, #billing_zip {
        font-size: 14px !important;
      }
    }

    @media (max-width: 576px) {
      #californiaPlansGrid .col-lg-4 {
        margin-bottom: 1rem;
      }
      
      #otherStatesPlansGrid .col-lg-6 {
        margin-bottom: 1rem;
      }
      
      /* Stack buttons vertically on very small screens */
      .d-flex.justify-content-between {
        flex-direction: column;
        gap: 0.5rem;
      }
      
      .d-flex.justify-content-between .btn {
        width: 100%;
      }
      
      /* Payment form on mobile */
      .row.g-3.mb-3 .col-md-4 {
        margin-bottom: 1rem;
      }
    }
    
    /* User info form styling */
    #reviewFirstName, #reviewLastName, #reviewEmail, #reviewPhone {
      border: 1px solid #6f7379 !important;
      border-radius: 6px !important;
      padding: 8px 12px !important;
      font-size: 14px !important;
    }
    
    #reviewFirstName:focus, #reviewLastName:focus, 
    #reviewEmail:focus, #reviewPhone:focus {
      border-color: #1AA3A9 !important;
      box-shadow: 0 0 0 0.2rem rgba(26, 163, 169, 0.25) !important;
    }
    
    #updateUserInfo {
      transition: all 0.2s ease;
    }
    
    #updateUserInfo:hover {
      background-color: #157347 !important;
      transform: translateY(-1px);
    }
    
    /* Payment form error styling */
    #dc_num_err {
      font-size: 12px;
      margin-top: -8px;
      margin-bottom: 12px !important;
    }
    
    /* Payment button loading state */
    #omcPayNow .spinner-border {
      width: 1rem;
      height: 1rem;
      border-width: 0.15em;
    }
  `;
  document.head.appendChild(style);

  // Init
  setupDOB();
addDebugButton();
if (document.querySelector('#step-9:not(.d-none)')) {
    setTimeout(setupSIMPayment, 500);
}
  if (currentUser && currentUser.is_logged_in){
    app.user.email   = currentUser.email || '';
    app.user.name    = currentUser.name || '';
    app.user.phone   = currentUser.phone || '';

    if (app.user.email){
      qs('#userNameBadge').textContent = app.user.email;
      qs('#userNameBadge').classList.remove('d-none');
    }
    if (qs('#confirmEmail')) qs('#confirmEmail').textContent = app.user.email;

    showStep(3);
  } else {
    showStep(1);
  }


  // Add debug button for testing
function addDebugButton() {
    const debugBtn = document.createElement('button');
    debugBtn.id = 'debugPayBtn';
    debugBtn.className = 'btn btn-warning btn-sm position-fixed';
    debugBtn.style = 'bottom: 20px; right: 20px; z-index: 9999;';
    debugBtn.textContent = 'Debug Payment';
    debugBtn.onclick = function() {
        console.log('=== DEBUG INFO ===');
        console.log('App State:', app);
        console.log('Amount:', getTotal());
        console.log('Payment Button:', qs('#omcPayNow'));
        console.log('Current Step:', document.querySelector('.step:not(.d-none)')?.id);
        console.log('User Info:', app.user);
        
        // Test AJAX endpoint
        fetch(ajaxURL, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'omc_generate_fingerprint',
                nonce: nonce,
                amount: 10.00,
                sequence: 123,
                timestamp: Math.floor(Date.now() / 1000)
            })
        })
        .then(r => r.json())
        .then(data => console.log('Fingerprint Test:', data))
        .catch(err => console.error('Test Error:', err));
    };
    
    document.body.appendChild(debugBtn);
}

// Initialize debug
addDebugButton();
})(jQuery);

function formatPhone(input) {
  let num = input.value.replace(/\D/g, '');
  if (num.length > 0) {
    num = num.substring(0, 10);
    let formatted = '(' + num.substring(0,3);
    if (num.length >= 4) formatted += ') ' + num.substring(3,6);
    if (num.length >= 7) formatted += '-' + num.substring(6,10);
    input.value = formatted;
  }
}