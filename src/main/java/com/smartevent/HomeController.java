package com.smartevent;

import java.util.List;
import java.util.Map;

import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

@Controller
public class HomeController {

    private final BookingRepository bookingRepository;

    public HomeController(BookingRepository bookingRepository) {
        this.bookingRepository = bookingRepository;
    }

    @GetMapping("/")
    public String home() {
        return "index";
    }

    @PostMapping("/login")
    public String login(@RequestParam(required = false) String email,
                        @RequestParam(required = false) String password) {
        return "redirect:/booking";
    }

    @PostMapping("/register")
    public String register(@RequestParam(required = false) String fullName,
                           @RequestParam(required = false) String email,
                           @RequestParam(required = false) String phone,
                           @RequestParam(required = false) String password) {
        return "redirect:/booking";
    }

    @GetMapping("/booking")
    public String bookingPage(Model model) {
        model.addAttribute("bookings", bookingRepository.findAllByOrderByIdDesc());
        return "booking";
    }

    @PostMapping("/api/bookings")
    @ResponseBody
    public ResponseEntity<?> createBooking(@RequestBody Booking booking) {
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
            booking.setCustomerName("Guest Customer");
        }
        if (booking.getCustomerEmail() == null || booking.getCustomerEmail().isBlank()) {
            booking.setCustomerEmail("guest@gmail.com");
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
}