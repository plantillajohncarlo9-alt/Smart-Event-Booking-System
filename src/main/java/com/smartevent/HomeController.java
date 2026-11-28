package com.smartevent;

import java.util.List;
import java.util.Map;
import java.util.Optional;

import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;
import org.springframework.web.server.ResponseStatusException;

import jakarta.persistence.Column;
import jakarta.persistence.Entity;
import jakarta.persistence.GeneratedValue;
import jakarta.persistence.GenerationType;
import jakarta.persistence.Id;
import jakarta.persistence.Table;
import jakarta.servlet.http.HttpSession;

@Controller
public class HomeController {

    private static final String SESSION_USER = "loggedInUser";

    private final BookingRepository bookingRepository;
    private final CustomerUserRepository customerUserRepository;

    public HomeController(BookingRepository bookingRepository, CustomerUserRepository customerUserRepository) {
        this.bookingRepository = bookingRepository;
        this.customerUserRepository = customerUserRepository;
    }

    @GetMapping("/")
    public String home() {
        return "index";
    }

    @GetMapping("/login")
    public String loginPage(@RequestParam(value = "registered", required = false) String registered,
                            @RequestParam(value = "error", required = false) String error,
                            @RequestParam(value = "logout", required = false) String logout,
                            Model model) {
        model.addAttribute("authMode", "login");
        model.addAttribute("authenticated", false);
        model.addAttribute("bookings", List.of());
        if (registered != null) {
            model.addAttribute("successMessage", "Account created successfully. Please log in with your credentials.");
        }
        if (error != null) {
            model.addAttribute("errorMessage", "Invalid email/username or password. Please try again.");
        }
        if (logout != null) {
            model.addAttribute("successMessage", "You have been logged out.");
        }
        return "booking";
    }

    @PostMapping("/login")
    public String login(@RequestParam(name = "identifier", required = false) String identifier,
                        @RequestParam(name = "email", required = false) String emailParam,
                        @RequestParam(name = "password", required = false) String password,
                        HttpSession session,
                        Model model) {
        String loginValue = (identifier != null && !identifier.isBlank()) ? identifier.trim() :
                            (emailParam != null ? emailParam.trim() : "");

        model.addAttribute("authMode", "login");
        model.addAttribute("authenticated", false);
        model.addAttribute("bookings", List.of());

        if (loginValue.isEmpty() || password == null || password.isBlank()) {
            model.addAttribute("errorMessage", "Please enter your email/username and password.");
            return "booking";
        }

        Optional<CustomerUser> foundUser = customerUserRepository.findByEmailIgnoreCaseOrUsernameIgnoreCase(loginValue, loginValue);
        if (foundUser.isEmpty() || !foundUser.get().getPassword().equals(password)) {
            model.addAttribute("errorMessage", "Invalid email/username or password.");
            return "booking";
        }

        CustomerUser user = foundUser.get();
        session.setAttribute(SESSION_USER, user.getEmail());
        session.setAttribute("loggedInUserName", user.getFullName());
        session.setAttribute("loggedInUserId", user.getId());
        return "redirect:/booking";
    }

    @GetMapping("/register")
    public String registerPage(Model model) {
        model.addAttribute("authMode", "register");
        model.addAttribute("authenticated", false);
        model.addAttribute("bookings", List.of());
        return "booking";
    }

    @PostMapping("/register")
    public String register(@RequestParam("fullName") String fullName,
                           @RequestParam(name = "username", required = false) String username,
                           @RequestParam("email") String email,
                           @RequestParam("phone") String phone,
                           @RequestParam("password") String password,
                           Model model) {
        String cleanName = fullName != null ? fullName.trim() : "";
        String cleanEmail = email != null ? email.trim().toLowerCase() : "";
        String cleanPhone = phone != null ? phone.trim() : "";
        String cleanPassword = password != null ? password : "";
        String cleanUsername = (username != null && !username.isBlank())
                ? username.trim()
                : cleanEmail.split("@")[0];

        model.addAttribute("authMode", "register");
        model.addAttribute("authenticated", false);
        model.addAttribute("bookings", List.of());

        if (cleanName.isEmpty() || cleanEmail.isEmpty() || cleanPhone.isEmpty() || cleanPassword.isEmpty()) {
            model.addAttribute("errorMessage", "Please fill in all required registration fields.");
            return "booking";
        }

        if (customerUserRepository.existsByEmailIgnoreCase(cleanEmail)) {
            model.addAttribute("errorMessage", "An account with this email address already exists.");
            return "booking";
        }

        if (customerUserRepository.existsByUsernameIgnoreCase(cleanUsername)) {
            model.addAttribute("errorMessage", "That username is already taken. Please choose another one.");
            return "booking";
        }

        CustomerUser newUser = new CustomerUser(cleanName, cleanUsername, cleanEmail, cleanPhone, cleanPassword);
        customerUserRepository.save(newUser);
        return "redirect:/login?registered=true";
    }

    @GetMapping("/logout")
    public String logout(HttpSession session) {
        if (session != null) {
            session.invalidate();
        }
        return "redirect:/login?logout=true";
    }

    @GetMapping("/booking")
    public String bookingPage(HttpSession session, Model model) {
        Object loggedInUser = session.getAttribute(SESSION_USER);
        if (loggedInUser == null) {
            return "redirect:/login";
        }
        model.addAttribute("authenticated", true);
        model.addAttribute("authMode", "login");
        model.addAttribute("bookings", bookingRepository.findAllByOrderByIdDesc());
        model.addAttribute("customerName", session.getAttribute("loggedInUserName"));
        model.addAttribute("customerEmail", loggedInUser.toString());
        return "booking";
    }

    @PostMapping("/api/bookings")
    @ResponseBody
    public ResponseEntity<?> createBooking(@RequestBody Booking booking, HttpSession session) {
        Object loggedInUser = session.getAttribute(SESSION_USER);
        if (loggedInUser == null) {
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED)
                    .body(Map.of("message", "Please log in before creating a booking."));
        }

        if (booking.getVenueArea() != null && booking.getEventDate() != null && booking.getTimeSlot() != null) {
            boolean duplicate = bookingRepository.existsByVenueAreaAndEventDateAndTimeSlot(
                    booking.getVenueArea(),
                    booking.getEventDate(),
                    booking.getTimeSlot()
            );
            if (duplicate) {
                return ResponseEntity.status(HttpStatus.CONFLICT)
                        .body(Map.of("message", "Conflict detected: May naka-book na sa napiling Venue Area, Date, at Time Slot."));
            }
        }

        if (booking.getPaymentStatus() == null || booking.getPaymentStatus().isBlank()) {
            booking.setPaymentStatus("Unpaid");
        }
        if (booking.getBookingStatus() == null || booking.getBookingStatus().isBlank()) {
            booking.setBookingStatus("Pending");
        }
        if (booking.getCustomerName() == null || booking.getCustomerName().isBlank()) {
            Object sessionName = session.getAttribute("loggedInUserName");
            booking.setCustomerName(sessionName != null ? sessionName.toString() : "Guest Customer");
        }
        if (booking.getCustomerEmail() == null || booking.getCustomerEmail().isBlank()) {
            booking.setCustomerEmail(loggedInUser.toString());
        }

        Booking saved = bookingRepository.save(booking);
        return ResponseEntity.status(HttpStatus.CREATED).body(saved);
    }

    @GetMapping("/admin")
    public String adminLoginPage() {
        return "admin";
    }

    @PostMapping("/admin/login")
    public String adminLogin(@RequestParam(required = false) String username,
                             @RequestParam(required = false) String password) {
        return "redirect:/admin/dashboard";
    }

    @GetMapping("/admin/dashboard")
    public String adminDashboard(Model model) {
        List<Booking> bookings = bookingRepository.findAllByOrderByIdDesc();
        model.addAttribute("bookings", bookings);
        return "admin-dashboard";
    }

    @PostMapping("/admin/bookings/update")
    @ResponseBody
    public ResponseEntity<String> updateBookingStatus(
            @RequestParam Long id,
            @RequestParam String paymentStatus,
            @RequestParam String bookingStatus) {
        Booking booking = bookingRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Booking not found"));
        booking.setPaymentStatus(paymentStatus);
        booking.setBookingStatus(bookingStatus);
        bookingRepository.save(booking);
        return ResponseEntity.ok("OK");
    }

    @PostMapping("/admin/bookings/delete")
    @ResponseBody
    public ResponseEntity<String> deleteBooking(@RequestParam Long id) {
        if (!bookingRepository.existsById(id)) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Booking not found");
        }
        bookingRepository.deleteById(id);
        return ResponseEntity.ok("OK");
    }

    @Entity
    @Table(name = "users")
    public static class CustomerUser {

        @Id
        @GeneratedValue(strategy = GenerationType.IDENTITY)
        private Long id;

        @Column(nullable = false)
        private String fullName;

        @Column(nullable = false, unique = true)
        private String username;

        @Column(nullable = false, unique = true)
        private String email;

        @Column(nullable = false)
        private String phone;

        @Column(nullable = false)
        private String password;

        public CustomerUser() {
        }

        public CustomerUser(String fullName, String username, String email, String phone, String password) {
            this.fullName = fullName;
            this.username = username;
            this.email = email;
            this.phone = phone;
            this.password = password;
        }

        public Long getId() {
            return id;
        }

        public void setId(Long id) {
            this.id = id;
        }

        public String getFullName() {
            return fullName;
        }

        public void setFullName(String fullName) {
            this.fullName = fullName;
        }

        public String getUsername() {
            return username;
        }

        public void setUsername(String username) {
            this.username = username;
        }

        public String getEmail() {
            return email;
        }

        public void setEmail(String email) {
            this.email = email;
        }

        public String getPhone() {
            return phone;
        }

        public void setPhone(String phone) {
            this.phone = phone;
        }

        public String getPassword() {
            return password;
        }

        public void setPassword(String password) {
            this.password = password;
        }
    }
}

interface CustomerUserRepository extends JpaRepository<HomeController.CustomerUser, Long> {
    Optional<HomeController.CustomerUser> findByEmailIgnoreCaseOrUsernameIgnoreCase(String email, String username);
    boolean existsByEmailIgnoreCase(String email);
    boolean existsByUsernameIgnoreCase(String username);
}
