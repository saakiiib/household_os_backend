@extends('frontend.master')
@section('title', 'Contact')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="text-center mb-5">
                    <h2 class="fw-bold">Get in Touch</h2>
                    <p class="text-muted">We'd love to hear from you</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="bg-white rounded-3 border p-4 text-center mb-3">
                            <i class="ri-map-pin-line" style="font-size:28px; color:#1a3c6e;"></i>
                            <div class="fw-semibold mt-2">Address</div>
                            <div class="text-muted small">123 Mirpur Road, Dhaka 1216</div>
                        </div>
                        <div class="bg-white rounded-3 border p-4 text-center mb-3">
                            <i class="ri-phone-line" style="font-size:28px; color:#1a3c6e;"></i>
                            <div class="fw-semibold mt-2">Phone</div>
                            <div class="text-muted small">+880 1700-000000</div>
                        </div>
                        <div class="bg-white rounded-3 border p-4 text-center">
                            <i class="ri-mail-line" style="font-size:28px; color:#1a3c6e;"></i>
                            <div class="fw-semibold mt-2">Email</div>
                            <div class="text-muted small">hello@shopbd.com</div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="bg-white rounded-3 border p-4">
                            <h5 class="fw-bold mb-4">Send a Message</h5>
                            <div class="mb-3">
                                <label class="form-label">Your Name</label>
                                <input type="text" id="contact_name" class="form-control" placeholder="John Doe">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" id="contact_email" class="form-control" placeholder="you@email.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" id="contact_subject" class="form-control"
                                    placeholder="How can we help?">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea id="contact_message" class="form-control" rows="4" placeholder="Write your message..."></textarea>
                            </div>
                            <button class="btn btn-primary px-4" id="contactSendBtn">
                                <i class="ri-send-plane-line me-1"></i> Send Message
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $('#contactSendBtn').on('click', function() {
            showSuccess('Message sent! We will get back to you shortly.');
            $('#contact_name, #contact_email, #contact_subject, #contact_message').val('');
        });
    </script>
@endsection
